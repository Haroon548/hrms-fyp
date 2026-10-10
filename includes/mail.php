<?php
/*
 * Sending email (D9, FR-MAIL-01, FR-MAIL-02, FR-MAIL-07).
 * Uses PHPMailer (files in includes/vendor/) and the SMTP account in config/config.php.
 *
 * A failed email never stops a booking, order or sign-up (D4): send_email() just
 * returns false and the page carries on. Every email is also saved as an HTML file
 * in logs/emails/, so codes and links can be tested on a computer with no email set up.
 * The logo is sent inside the email (not linked), so it shows in Gmail even when the
 * site runs on localhost. admin/email-test.php sends a test email and explains errors.
 */

require_once __DIR__ . '/vendor/phpmailer-7.1.1/Exception.php';
require_once __DIR__ . '/vendor/phpmailer-7.1.1/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer-7.1.1/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

// True once real SMTP details are in config.php (not the example placeholders).
function email_is_set_up()
{
    return SMTP_USER !== '' && SMTP_PASS !== ''
        && strpos(SMTP_USER, 'your-') !== 0 && strpos(SMTP_PASS, 'your-') !== 0;
}

/*
 * Sends one email. $html is the inside of the email (it goes into email_layout()),
 * $text is the plain-text version for email apps that don't show HTML.
 * Returns true if it was sent.
 */
function send_email($toEmail, $toName, $subject, $html, $text)
{
    email_error('');
    save_email_copy($toEmail, $subject, email_layout($subject, $html, SITE_URL . '/assets/images/logo-white.png'));

    if (!email_is_set_up()) {
        email_error('Email isn\'t set up yet: SMTP_USER and SMTP_PASS in config/config.php still have the example values.');
        error_log('Email not sent (SMTP not set up in config.php): "' . $subject . '" to ' . $toEmail);
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // port 587
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->Timeout = 10; // don't keep the visitor waiting if the mail server is slow
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addReplyTo(HOTEL_EMAIL, HOTEL_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->XMailer = ' '; // don't advertise the mail library
        $logo = __DIR__ . '/../assets/images/logo-white.png';
        if (is_file($logo)) {
            $mail->addEmbeddedImage($logo, 'logo', 'ramada-logo.png');
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = email_layout($subject, $html, is_file($logo) ? 'cid:logo' : SITE_URL . '/assets/images/logo-white.png');
        $mail->AltBody = $text . "\n\n--\n" . HOTEL_NAME . "\n" . HOTEL_ADDRESS . "\n" . HOTEL_PHONE;
        $mail->send();
        return true;
    } catch (MailException $error) {
        // Logged for the developer; the visitor never sees a technical error (FR-MAIL-02).
        error_log('Email failed: "' . $subject . '" to ' . $toEmail . ': ' . $mail->ErrorInfo);
        email_error(email_problem_in_plain_words($mail->ErrorInfo));
        return false;
    }
}

// The reason the last email failed, in plain words ('' if it was sent). Set with email_error('...').
function email_error($set = null)
{
    static $last = '';
    if ($set !== null) {
        $last = $set;
    }
    return $last;
}

// Turns the mail server's technical error into something the hotel can act on.
function email_problem_in_plain_words($error)
{
    if (stripos($error, 'authenticate') !== false || stripos($error, 'Username and Password') !== false) {
        return 'Gmail refused the login. Check SMTP_USER (the full Gmail address) and SMTP_PASS (the 16-letter app password, not the normal password), and that 2-Step Verification is on for that Gmail account.';
    }
    if (stripos($error, 'connect') !== false) {
        return 'Couldn\'t reach the mail server (' . SMTP_HOST . ':' . SMTP_PORT . '). Check the internet connection; some hosts block port 587.';
    }
    if (stripos($error, 'certificate') !== false || stripos($error, 'SSL') !== false) {
        return 'The secure connection to the mail server failed (certificate problem on this computer or host).';
    }
    if (stripos($error, 'recipient') !== false) {
        return 'The mail server didn\'t accept the address it was sent to. Check the email address.';
    }
    return 'The email could not be sent: ' . $error;
}

// Keeps a copy of each email in logs/emails/ (protected from the web by .htaccess).
function save_email_copy($toEmail, $subject, $html)
{
    $folder = __DIR__ . '/../logs/emails';
    if (!is_dir($folder) && !@mkdir($folder, 0775, true)) {
        return;
    }
    // logs/ isn't in GitHub, so create its "no browser access" rule the first time
    $rule = __DIR__ . '/../logs/.htaccess';
    if (!is_file($rule)) {
        @file_put_contents($rule, "# Nothing in this folder should be opened in a browser.\nRequire all denied\n");
    }
    $name = date('Y-m-d_His') . '_' . preg_replace('/[^a-z0-9]+/i', '-', $subject) . '.html';
    @file_put_contents($folder . '/' . $name, "<!-- To: " . e($toEmail) . " -->\n" . $html);
}

// The hotel's email design: red header with the white logo, white card, address in the footer.
// Tables and inline styles because many email apps ignore normal CSS.
function email_layout($title, $content, $logoSrc)
{
    $logo = e($logoSrc);
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . e($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F5F6F6;font-family:Arial,Helvetica,sans-serif;color:#333333;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F6F6;padding:24px 0;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;">'
        . '<tr><td style="background:#D21242;padding:22px 30px;" align="center"><img src="' . $logo . '" alt="' . e(HOTEL_NAME) . '" width="160" style="display:block;border:0;"></td></tr>'
        . '<tr><td style="padding:32px 30px;font-size:15px;line-height:1.7;">' . $content . '</td></tr>'
        . '<tr><td style="background:#352126;color:#FFFFFF;padding:20px 30px;font-size:13px;line-height:1.6;" align="center">'
        . e(HOTEL_NAME) . '<br>' . e(HOTEL_ADDRESS) . '<br>' . e(HOTEL_PHONE) . ' &middot; ' . e(HOTEL_EMAIL)
        . '</td></tr></table></td></tr></table></body></html>';
}

// A big red button for links inside emails.
function email_button($link, $label)
{
    return '<p style="margin:26px 0;"><a href="' . e($link) . '" style="display:inline-block;background:#D21242;color:#FFFFFF;'
        . 'text-decoration:none;font-weight:bold;padding:14px 28px;">' . e($label) . '</a></p>'
        . '<p style="font-size:12px;color:#707070;">If the button doesn\'t work, copy this link into your browser:<br>'
        . '<span style="word-break:break-all;">' . e($link) . '</span></p>';
}

/* ---------- The account emails ---------- */

// A 6-digit code for confirming a new account, resetting a forgotten password or changing it.
function send_code_email($user, $code, $purpose)
{
    $first = explode(' ', trim($user['name']))[0];
    $what = [
        'verify'   => ['Confirm your email', 'Welcome, ' . $first . '! Enter this code to confirm your email address and finish creating your ' . HOTEL_NAME . ' account:', 'verification code'],
        'reset'    => ['Reset your password', 'Hello ' . $first . ', we received a request to reset the password for your ' . HOTEL_NAME . ' account. Enter this code to choose a new password:', 'password reset code'],
        'password' => ['Confirm your new password', 'Hello ' . $first . ', you asked to change the password for your ' . HOTEL_NAME . ' account. Enter this code to confirm it\'s you:', 'security code'],
        'email'    => ['Confirm your new email', 'Hello ' . $first . ', you asked to use this address for your ' . HOTEL_NAME . ' account. Enter this code to confirm it:', 'email change code'],
    ][$purpose];
    $spaced = substr($code, 0, 3) . ' ' . substr($code, 3);
    $html = '<h1 style="font-size:22px;color:#D21242;font-weight:normal;margin:0 0 16px;">' . e($what[0]) . '</h1>'
        . '<p>' . e($what[1]) . '</p>'
        . '<p style="margin:26px 0;text-align:center;"><span style="display:inline-block;padding:16px 28px;background:#F5F6F6;border:1px solid #EDEDED;'
        . 'font-size:34px;font-weight:bold;letter-spacing:8px;color:#3D3935;font-family:Consolas,Menlo,monospace;">' . $spaced . '</span></p>'
        . '<p>The code works for ' . CODE_MINUTES . ' minutes. Never share it: hotel staff will never ask you for it.</p>'
        . '<p style="color:#707070;font-size:13px;">' . ($purpose === 'verify'
            ? 'If you didn\'t create an account, you can ignore this email.'
            : ($purpose === 'email' ? 'If you didn\'t ask for this, ignore this email: nothing changes.'
            : 'If you didn\'t ask for this, ignore this email: your password stays the same. If it keeps happening, call us on ' . e(HOTEL_PHONE) . '.')) . '</p>';
    $text = $what[0] . "\n\n" . $what[1] . "\n\n    $spaced\n\nThe code works for " . CODE_MINUTES . " minutes. Never share it.\n"
        . ($purpose === 'verify' ? "If you didn't create an account, ignore this email." : "If you didn't ask for this, ignore this email: your password stays the same.");
    // The code leads the subject, so it can be read from the phone's notification
    return send_email($user['email'], $user['name'], $code . ' is your ' . HOTEL_NAME . ' ' . $what[2], $html, $text);
}

// Security alert to the OLD address after the account's email is changed (FR-AUTH-14)
function send_email_changed_email($user, $oldEmail)
{
    $first = explode(' ', trim($user['name']))[0];
    $when = date('l j F Y, g:i A');
    $html = '<h1 style="font-size:22px;color:#D21242;font-weight:normal;margin:0 0 16px;">Your email was changed</h1>'
        . '<p>Hello ' . e($first) . ', the email for your ' . e(HOTEL_NAME) . ' account was changed on ' . e($when) . ' (Pakistan time) from <strong>' . e($oldEmail) . '</strong> to <strong>' . e($user['email']) . '</strong>.</p>'
        . '<p>From now on, log in and receive codes and confirmations at the new address.</p>'
        . '<p><strong>If this wasn\'t you,</strong> call us straight away on ' . e(HOTEL_PHONE) . '.</p>';
    $text = "Your email was changed\n\nThe email for your " . HOTEL_NAME . " account was changed on $when from $oldEmail to {$user['email']}.\n"
        . "If this wasn't you, call us on " . HOTEL_PHONE . '.';
    return send_email($oldEmail, $user['name'], 'Your email was changed', $html, $text);
}

// Security alert after any password change (FR-AUTH-13)
function send_password_changed_email($user)
{
    $first = explode(' ', trim($user['name']))[0];
    $when = date('l j F Y, g:i A');
    $html = '<h1 style="font-size:22px;color:#D21242;font-weight:normal;margin:0 0 16px;">Your password was changed</h1>'
        . '<p>Hello ' . e($first) . ', the password for your ' . e(HOTEL_NAME) . ' account (' . e($user['email']) . ') was changed on ' . e($when) . ' (Pakistan time).</p>'
        . '<p>If this was you, there\'s nothing to do.</p>'
        . '<p><strong>If it wasn\'t you,</strong> reset your password straight away and call us on ' . e(HOTEL_PHONE) . '.</p>'
        . email_button(SITE_URL . '/account/forgot-password.php', 'Reset my password');
    $text = "Your password was changed\n\nThe password for your " . HOTEL_NAME . ' account (' . $user['email'] . ") was changed on $when.\n"
        . "If it wasn't you, reset it now: " . SITE_URL . '/account/forgot-password.php and call ' . HOTEL_PHONE . '.';
    return send_email($user['email'], $user['name'], 'Your password was changed', $html, $text);
}
