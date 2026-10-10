<?php
/*
 * Loaded at the top of every page: settings and shared helper functions.
 */

if (!file_exists(__DIR__ . '/../config/config.php')) {
    exit('Setup needed: copy config/config.example.php to config/config.php and fill it in.');
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

// Pakistan time for every date the site shows or saves
date_default_timezone_set('Asia/Karachi');

// Session cookie: not readable by JavaScript, not sent from other sites, HTTPS-only when the site uses HTTPS.
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

require_once __DIR__ . '/auth.php';

/* ---------- Links ---------- */

// The site's folder in the address, e.g. "/hrms-fyp" on XAMPP or "" on a live domain.
// Worked out from the page being opened, so pages in subfolders (admin/) still find
// the CSS, images and other pages.
function base_path()
{
    static $base = null;
    if ($base === null) {
        $root = realpath(__DIR__ . '/..');
        $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
        $depth = ($scriptDir !== false && strpos($scriptDir, $root) === 0)
            ? substr_count(substr($scriptDir, strlen($root)), DIRECTORY_SEPARATOR)
            : 0;
        $urlDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        for ($i = 0; $i < $depth; $i++) {
            $urlDir = dirname($urlDir);
        }
        $base = rtrim($urlDir, '/');
    }
    return $base;
}

// Link to a file in the project, e.g. url('account/login.php') or url('assets/css/style.css').
function url($path = '')
{
    return base_path() . '/' . ltrim($path, '/');
}

function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

/* ---------- Output ---------- */

// Escape text before printing it in HTML (protects against XSS, NFR-02).
function e($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

// Red title, grey subtitle and short divider used at the start of most sections.
function section_heading($title, $subtitle = '')
{
    echo '<div class="section-heading">';
    echo '<h2>' . e($title) . '</h2>';
    if ($subtitle !== '') {
        echo '<p class="subtitle">' . e($subtitle) . '</p>';
    }
    echo '<span class="divider"></span>';
    echo '</div>';
}

// Prints a photo from assets/images/. Until the hotel's photo is added,
// a grey placeholder with the photo's name is shown instead.
function photo($file, $alt, $class = '')
{
    $path = 'assets/images/' . $file;
    if ($file !== '' && is_file(__DIR__ . '/../' . $path)) {
        echo '<img src="' . e(url($path)) . '" alt="' . e($alt) . '" class="' . e($class) . '" loading="lazy">';
    } else {
        echo '<div class="photo-placeholder ' . e($class) . '" role="img" aria-label="' . e($alt) . '">';
        echo '<span><i class="fa-regular fa-image"></i> ' . e($alt) . '</span>';
        echo '</div>';
    }
}

// A photo from site_photos (or any row with file, alt_text, is_illustrative). Photos that aren't
// the hotel's own yet get a small "Illustrative photo" label (D22).
function site_photo_tag($photo, $class = '')
{
    photo($photo['file'], $photo['alt_text'], $class);
    if (!empty($photo['is_illustrative'])) {
        echo '<span class="illustrative-tag">Illustrative photo</span>';
    }
}

// Formats a price as "23,995".
function money($amount)
{
    return number_format((float) $amount);
}

// Small coloured status label (design.md: Status badges), used in My Account and the admin panel.
function status_badge($status)
{
    $styles = [
        'available'   => ['Available', 'badge-green'],
        'maintenance' => ['Under maintenance', 'badge-yellow'],
        'confirmed'   => ['Confirmed', 'badge-blue'],
        'checked_in'  => ['Checked in', 'badge-green'],
        'checked_out' => ['Checked out', 'badge-dark'],
        'cancelled'   => ['Cancelled', 'badge-grey'],
        'shown'       => ['On the website', 'badge-green'],
        'hidden'      => ['Hidden', 'badge-grey'],
        'pending'     => ['Received', 'badge-yellow'],
        'preparing'   => ['Preparing', 'badge-blue'],
        'ready'       => ['Ready', 'badge-green'],
        'completed'   => ['Completed', 'badge-dark'],
        'on_menu'     => ['On the menu', 'badge-green'],
        'sold_out'    => ['Sold out', 'badge-grey'],
        'seated'      => ['Seated', 'badge-dark'],
        'unpaid'      => ['Unpaid', 'badge-yellow'],
        'paid'        => ['Paid', 'badge-green'],
    ];
    [$label, $class] = $styles[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'badge-grey'];
    return '<span class="status-badge ' . $class . '">' . e($label) . '</span>';
}

/* ---------- Messages shown once on the next page ---------- */

// type: 'success', 'error' or 'info'
function flash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function show_flash()
{
    // A page reloading itself in the background (live.js) leaves messages for the visitor's next page
    if (empty($_SESSION['flash']) || is_live_request()) {
        return;
    }
    foreach ($_SESSION['flash'] as $item) {
        $icon = ['success' => 'fa-circle-check', 'error' => 'fa-circle-exclamation', 'info' => 'fa-circle-info'][$item['type']] ?? 'fa-circle-info';
        echo '<div class="notice notice-' . e($item['type']) . '" role="' . ($item['type'] === 'error' ? 'alert' : 'status') . '">';
        echo '<i class="fa-solid ' . $icon . '" aria-hidden="true"></i><span>' . e($item['message']) . '</span></div>';
    }
    unset($_SESSION['flash']);
}

/* ---------- Form protection (CSRF) ----------
   Every form that changes something includes a secret token from the session.
   A form on another website can't know it, so it can't submit forms as the user. */

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf()
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('This form has expired. Please go back, refresh the page and try again.');
    }
}

// The value a visitor typed, to refill a form after an error.
function old($field, $default = '')
{
    return e($_POST[$field] ?? $default);
}

/* ---------- Live updates (FR-LIVE-01, assets/js/live.js) ----------
   A page lists the tables it shows: live_watch('orders', 'invoices');
   The parts that should change by themselves carry data-live-region="name". */

function live_watch(...$tables)
{
    static $watched = [];
    $watched = array_values(array_unique(array_merge($watched, $tables)));
    return $watched;
}

// Attributes for <body>: what the page watches and the version of each table it was made from.
function live_body_attrs()
{
    $tables = live_watch();
    $known = $tables ? live_versions($tables) : null;
    if ($known === null) {
        return ''; // nothing to watch, or the live_versions table is missing
    }
    return ' data-live-url="' . e(url('live.php')) . '" data-live-watch="' . e(implode(',', $tables)) . '"'
        . ' data-live-known="' . e(json_encode((object) $known)) . '"'
        . ($_SERVER['REQUEST_METHOD'] === 'POST' ? ' data-live-posted' : '');
}

// True when live.js is loading the page again in the background.
function is_live_request()
{
    return ($_SERVER['HTTP_X_LIVE'] ?? '') === '1';
}
