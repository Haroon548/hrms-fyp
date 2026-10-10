<?php
/*
 * Layout for the login, register and password pages (design.md: Login / Register / Forgot password):
 * a hotel photo with a frosted quote on the left, the form card on the right.
 *
 * Usage:
 *   auth_open('Welcome back', 'Log in to manage your bookings', 'slides/slide-3.jpg');
 *   ... form ...
 *   auth_close();
 */

// Pages using this layout show messages inside the card, so they set $flash_inline = true
// before including the header.
function auth_open($title, $subtitle, $photo = 'slides/slide-1.jpg')
{
    ?>
    <section class="auth-page">
        <div class="auth-photo">
            <?php photo($photo, HOTEL_NAME); ?>
            <div class="auth-quote">
                <span class="stars" aria-label="4 star hotel">★★★★</span>
                <p>The Spot of Serenity &amp; Comfort in Multan</p>
                <small><?= e(HOTEL_ADDRESS) ?></small>
            </div>
        </div>
        <div class="auth-panel">
            <div class="auth-card">
                <h1><?= e($title) ?></h1>
                <?php if ($subtitle !== ''): ?>
                    <p class="auth-subtitle"><?= e($subtitle) ?></p>
                <?php endif; ?>
                <?php show_flash(); ?>
    <?php
}

function auth_close()
{
    ?>
            </div>
        </div>
    </section>
    <?php
}

// A password box with a show/hide eye. $meter adds the strength bar (for new passwords).
function password_input($name, $label, $autocomplete, $meter = false, $error = '')
{
    $id = 'f_' . $name;
    ?>
    <div class="form-field<?= $error ? ' has-error' : '' ?>">
        <label for="<?= $id ?>"><?= e($label) ?></label>
        <div class="password-box">
            <input type="password" id="<?= $id ?>" name="<?= e($name) ?>" autocomplete="<?= e($autocomplete) ?>" required
                <?= $meter ? 'data-strength minlength="8"' : '' ?><?= $error ? ' aria-invalid="true" aria-describedby="' . $id . '_error"' : '' ?>>
            <button type="button" class="password-eye" data-toggle-password="<?= $id ?>" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
        </div>
        <?php if ($meter): ?>
            <div class="strength" aria-hidden="true"><span></span></div>
            <p class="field-help" data-strength-text>At least 8 characters, with a letter and a number.</p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="field-error" id="<?= $id ?>_error"><?= e($error) ?></p>
        <?php endif; ?>
    </div>
    <?php
}

// A normal text/email/tel box with its label and error message.
function text_input($name, $label, $type, $autocomplete, $value, $error = '', $extra = '')
{
    $id = 'f_' . $name;
    ?>
    <div class="form-field<?= $error ? ' has-error' : '' ?>">
        <label for="<?= $id ?>"><?= e($label) ?></label>
        <input type="<?= e($type) ?>" id="<?= $id ?>" name="<?= e($name) ?>" value="<?= $value ?>" autocomplete="<?= e($autocomplete) ?>" required
            <?= $extra ?><?= $error ? ' aria-invalid="true" aria-describedby="' . $id . '_error"' : '' ?>>
        <?php if ($error): ?>
            <p class="field-error" id="<?= $id ?>_error"><?= e($error) ?></p>
        <?php endif; ?>
    </div>
    <?php
}

// The box for a 6-digit emailed code: big spaced digits, numbers keyboard on phones, and
// phones can fill it in straight from the email notification (autocomplete="one-time-code").
function code_input($error = '')
{
    ?>
    <div class="form-field code-field<?= $error ? ' has-error' : '' ?>">
        <label for="f_code">6-digit code</label>
        <input type="text" id="f_code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="7"
               required autofocus class="code-input" placeholder="••••••" data-code-input<?= $error ? ' aria-invalid="true" aria-describedby="f_code_error"' : '' ?>>
        <?php if ($error): ?>
            <p class="field-error" id="f_code_error"><?= e($error) ?></p>
        <?php endif; ?>
    </div>
    <?php
}

// "Send a new code", greyed out with a countdown until a new one may be sent.
function resend_code_button($wait, $action = 'resend')
{
    ?>
    <form method="post" class="resend-code">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= e($action) ?>">
        <span>Didn't get it? Check your spam folder, or</span>
        <button type="submit" class="link-button" data-resend-wait="<?= (int) $wait ?>"<?= $wait ? ' disabled' : '' ?>>Send a new code<?= $wait ? ' (' . (int) $wait . ' s)' : '' ?></button>
    </form>
    <?php
}
