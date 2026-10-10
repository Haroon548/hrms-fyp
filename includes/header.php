<?php
/*
 * Shared page header (design.md section 3, screenshot 01).
 * Before including, a page sets:
 *   $page_title       - text for the browser tab
 *   $active_page      - which menu link is red: home, about, rooms, dining, events, gallery, contact
 *   $page_description - (optional) one sentence shown by Google and in WhatsApp link previews
 *   $share_image      - (optional) photo in assets/images/ used in link previews
 *   $body_class       - (optional) e.g. 'page-home' for the see-through header over the hero
 *   $flash_inline     - (optional) true if the page shows messages itself (show_flash()) instead of as a pop-up
 *   $extra_styles     - (optional) extra CSS files for this page, e.g. the photo viewer's
 * and calls live_watch('table', ...) if parts of it ([data-live-region]) should update by themselves.
 */
$page_title = $page_title ?? HOTEL_NAME;
$active_page = $active_page ?? '';
$page_description = $page_description ?? 'Four-star hotel in the heart of Multan. Book rooms, order food and reserve a table online.';
$share_image = $share_image ?? 'slides/slide-1.jpg';
$body_class = $body_class ?? '';

$menu = [
    'home'    => ['Home', 'index.php'],
    'about'   => ['About Us', 'about.php'],
    'rooms'   => ['Rooms', 'rooms.php'],
    'dining'  => ['Dining', 'dining.php'],
    'events'  => ['Meeting & Events', 'events.php'],
    'gallery' => ['Gallery', 'gallery.php'],
    'contact' => ['Contact Us', 'contact.php'],
];

// Account menu for logged-in guests (design.md: Header). Tabs are built in later chunks.
$user = current_user();
$account_links = [
    ['fa-solid fa-bed', 'My Bookings', 'account/index.php?tab=bookings'],
    ['fa-solid fa-utensils', 'My Orders', 'account/index.php?tab=orders'],
    ['fa-regular fa-calendar-check', 'My Reservations', 'account/index.php?tab=reservations'],
    ['fa-solid fa-file-invoice', 'My Invoices', 'account/index.php?tab=invoices'],
    ['fa-regular fa-user', 'Profile & password', 'account/index.php?tab=profile'],
];
$first_name = $user ? explode(' ', trim($user['name']))[0] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?></title>
    <meta name="description" content="<?= e($page_description) ?>">
    <meta name="theme-color" content="#D21242">
    <!-- Link preview when the page is shared on WhatsApp, Facebook, etc. -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e(HOTEL_NAME) ?>">
    <meta property="og:title" content="<?= e($page_title) ?>">
    <meta property="og:description" content="<?= e($page_description) ?>">
    <meta property="og:image" content="<?= e(SITE_URL . '/assets/images/' . $share_image) ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/fontawesome-7.3.1/css/all.min.css') ?>">
    <?php foreach ($extra_styles ?? [] as $stylesheet): ?>
    <link rel="stylesheet" href="<?= e(asset($stylesheet)) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="<?= e($body_class) ?>"<?= live_body_attrs() ?>>

<a href="#main" class="skip-link">Skip to content</a>

<header class="site-header">
    <div class="container d-flex align-items-center justify-content-between h-100">
        <!-- Phone menu button (left on phones) -->
        <button class="menu-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu" aria-label="Open menu">
            <i class="fa-solid fa-bars"></i>
        </button>

        <a href="<?= url('index.php') ?>" class="site-logo" aria-label="<?= e(HOTEL_NAME) ?> home">
            <?php if (is_file(__DIR__ . '/../assets/images/logo.png')): ?>
                <img src="<?= url('assets/images/logo.png') ?>" alt="<?= e(HOTEL_NAME) ?>" class="logo-colour" width="175" height="66">
                <?php if (is_file(__DIR__ . '/../assets/images/logo-white.png')): ?>
                    <!-- White version, shown while the header is see-through over the Home slideshow -->
                    <img src="<?= url('assets/images/logo-white.png') ?>" alt="" class="logo-white" width="175" height="66" aria-hidden="true">
                <?php endif; ?>
            <?php else: ?>
                <span class="logo-text">RAMADA<small>BY WYNDHAM</small><strong>MULTAN</strong></span>
            <?php endif; ?>
        </a>

        <nav class="main-nav d-none d-lg-flex" aria-label="Main menu">
            <?php foreach ($menu as $key => [$label, $link]): ?>
                <a href="<?= url($link) ?>" class="<?= $key === $active_page ? 'active' : '' ?>"<?= $key === $active_page ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="header-actions d-none d-lg-flex">
            <a href="<?= url('rooms.php') ?>" class="btn-book">BOOK NOW</a>
            <?php if ($user): ?>
                <div class="dropdown account-menu">
                    <button class="header-login dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="account-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($first_name, 0, 1))) ?></span><?= e($first_name) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($user['role'] === 'admin'): ?>
                            <li><a class="dropdown-item" href="<?= url('admin/index.php') ?>"><i class="fa-solid fa-gauge"></i> Admin Panel</a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <?php foreach ($account_links as [$icon, $label, $link]): ?>
                            <li><a class="dropdown-item" href="<?= url($link) ?>"><i class="<?= $icon ?>"></i> <?= e($label) ?></a></li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="<?= url('account/logout.php') ?>" method="post"><?= csrf_field() ?>
                                <button type="submit" class="dropdown-item"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="<?= url('account/login.php') ?>" class="header-login">Login</a>
            <?php endif; ?>
        </div>

        <!-- Ramada's phone header has no way to book; ours keeps it one tap away -->
        <a href="<?= url('rooms.php') ?>" class="btn-book btn-book-phone d-lg-none">BOOK</a>
    </div>
</header>

<!-- Phone menu: red panel sliding in from the left (screenshot 14) -->
<div class="offcanvas offcanvas-start mobile-menu" tabindex="-1" id="mobileMenu" aria-label="Menu">
    <div class="offcanvas-body">
        <button type="button" class="btn-close btn-close-white ms-auto d-block mb-2" data-bs-dismiss="offcanvas" aria-label="Close menu"></button>
        <?php foreach ($menu as $key => [$label, $link]): ?>
            <a href="<?= url($link) ?>" class="<?= $key === $active_page ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <div class="mobile-menu-actions">
            <a href="<?= url('rooms.php') ?>">BOOK NOW</a>
            <?php if ($user): ?>
                <p class="mobile-menu-label">Hi, <?= e($first_name) ?></p>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= url('admin/index.php') ?>">Admin Panel</a>
                <?php endif; ?>
                <?php foreach ($account_links as [$icon, $label, $link]): ?>
                    <a href="<?= url($link) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
                <form action="<?= url('account/logout.php') ?>" method="post"><?= csrf_field() ?>
                    <button type="submit" class="mobile-menu-logout">Log out</button>
                </form>
            <?php else: ?>
                <a href="<?= url('account/login.php') ?>">Login</a>
                <a href="<?= url('account/register.php') ?>">Create an account</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<main id="main" tabindex="-1">
<?php if (empty($flash_inline) && !empty($_SESSION['flash'])): ?>
    <!-- Messages from the last action (e.g. "You have logged out"), shown as a pop-up note -->
    <div class="toast-area"><?php show_flash(); ?></div>
<?php endif; ?>
