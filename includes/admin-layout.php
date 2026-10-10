<?php
/*
 * Layout for the admin panel (design.md: Admin panel): a dark sidebar with the hotel logo
 * and links on the left, the white work area on the right. On phones and tablets the
 * sidebar slides in from the left (Bootstrap offcanvas).
 *
 * Usage, at the top of an admin page after require_admin():
 *   live_watch('room_types', 'rooms');   // optional: the tables the page shows (live updates)
 *   admin_open('Room types', 'room-types', 'Prices, guests, amenities and photos');
 *   ... page content ...
 *   admin_close();
 * The work area and the sidebar's counts update by themselves when a watched table changes.
 */

// Small counts next to sidebar links: new orders for the kitchen, unread messages
function admin_menu_counts()
{
    $counts = ['orders' => 0, 'messages' => 0];
    $counts['orders'] = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    $counts['messages'] = (int) db()->query('SELECT COUNT(*) FROM contact_messages WHERE is_handled = 0')->fetchColumn();
    return $counts;
}

// Sidebar links. More are added as each part of the admin panel is built.
function admin_menu()
{
    return [
        'dashboard'  => ['fa-solid fa-gauge', 'Dashboard', 'admin/index.php'],
        'bookings'   => ['fa-regular fa-calendar-check', 'Bookings', 'admin/bookings.php'],
        'occupancy'  => ['fa-solid fa-chart-gantt', 'Occupancy', 'admin/occupancy.php'],
        'orders'     => ['fa-solid fa-receipt', 'Orders', 'admin/orders.php'],
        'kitchen'    => ['fa-solid fa-fire-burner', 'Kitchen screen', 'admin/kitchen.php'],
        'invoices'   => ['fa-solid fa-file-invoice', 'Invoices', 'admin/invoices.php'],
        'reservations' => ['fa-solid fa-champagne-glasses', 'Reservations', 'admin/reservations.php'],
        'tables'     => ['fa-solid fa-chair', 'Tables & hours', 'admin/tables.php'],
        'menu'       => ['fa-solid fa-book-open', 'Menu', 'admin/menu.php'],
        'messages'   => ['fa-regular fa-envelope', 'Messages', 'admin/messages.php'],
        'halls'      => ['fa-solid fa-people-roof', 'Event halls', 'admin/halls.php'],
        'faqs'       => ['fa-regular fa-circle-question', 'FAQ', 'admin/faqs.php'],
        'email'      => ['fa-regular fa-paper-plane', 'Email check', 'admin/email-test.php'],
        'table-qr'   => ['fa-solid fa-qrcode', 'Table QR codes', 'admin/table-qr.php'],
        'room-types' => ['fa-solid fa-bed', 'Room types', 'admin/room-types.php'],
        'rooms'      => ['fa-solid fa-door-open', 'Rooms', 'admin/rooms.php'],
    ];
}

function admin_open($title, $active, $subtitle = '', $actions = '')
{
    $user = current_user();
    $menu = admin_menu();
    live_watch('orders', 'contact_messages'); // for the sidebar counts
    $counts = admin_menu_counts();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?> | Admin | <?= e(HOTEL_NAME) ?></title>
    <meta name="theme-color" content="#1B1416">
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/fontawesome-7.3.1/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="admin-body"<?= live_body_attrs() ?>>
<a href="#main" class="skip-link">Skip to content</a>

<div class="admin-shell">
    <aside class="admin-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="adminMenu" aria-label="Admin menu">
        <div class="admin-brand">
            <a href="<?= url('admin/index.php') ?>">
                <img src="<?= url('assets/images/logo-white.png') ?>" alt="<?= e(HOTEL_NAME) ?>" width="150" height="56">
            </a>
            <span>Admin panel</span>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#adminMenu" aria-label="Close menu"></button>
        </div>
        <nav class="admin-nav" aria-label="Admin pages" data-live-region="nav">
            <?php foreach ($menu as $key => [$icon, $label, $link]): ?>
                <a href="<?= url($link) ?>" class="<?= $key === $active ? 'active' : '' ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                    <i class="<?= $icon ?>" aria-hidden="true"></i> <?= e($label) ?>
                    <?php if (!empty($counts[$key])): ?>
                        <span class="admin-nav-count" title="<?= $key === 'orders' ? 'New orders' : 'Unread messages' ?>"><?= $counts[$key] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar-foot">
            <a href="<?= url('index.php') ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View website</a>
            <p><span class="account-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span> <?= e($user['name']) ?></p>
            <form action="<?= url('account/logout.php') ?>" method="post"><?= csrf_field() ?>
                <button type="submit"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</button>
            </form>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button class="admin-menu-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMenu" aria-controls="adminMenu" aria-label="Open admin menu">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <div class="admin-title">
                <h1><?= e($title) ?></h1>
                <?php if ($subtitle !== ''): ?>
                    <p><?= e($subtitle) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($actions !== ''): ?>
                <div class="admin-actions"><?= $actions ?></div>
            <?php endif; ?>
        </header>
        <main id="main" tabindex="-1" class="admin-content">
            <?php show_flash(); ?>
            <div data-live-region="main">
    <?php
}

function admin_close()
{
    ?>
            </div>
        </main>
    </div>
</div>

<script src="<?= url('assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('assets/js/main.js') ?>"></script>
<script src="<?= asset('assets/js/admin.js') ?>"></script>
<script src="<?= asset('assets/js/live.js') ?>"></script>
</body>
</html>
    <?php
}

/* Two people editing the same thing (e.g. a room type) at once: the form carries a stamp of
   what the record looked like when it was opened. If it has changed since, the save is
   stopped and the editor sees the latest version, instead of overwriting someone's change. */

function edit_stamp_field($row)
{
    return '<input type="hidden" name="edit_stamp" value="' . e($row ? md5(json_encode($row)) : '') . '">';
}

// True if $row (read fresh from the database) is not what the form was opened with
function edited_meanwhile($row)
{
    return $row && isset($_POST['edit_stamp']) && $_POST['edit_stamp'] !== md5(json_encode($row));
}

function edited_meanwhile_notice($what)
{
    return '<div class="notice notice-error" role="alert"><i class="fa-solid fa-user-pen" aria-hidden="true"></i><span>Someone else saved changes to '
        . e($what) . ' while you were editing, so your changes were <strong>not saved</strong>. Your version is still in the form: compare it with the page as it is now, then press Save again.</span></div>';
}

// The posted admin action, after the CSRF check. Pages switch on it.
function admin_action()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return '';
    }
    // Uploads bigger than the server allows arrive with an empty form
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', 'The photos were too big to upload all at once (the server allows ' . ini_get('post_max_size') . 'B in total). Upload fewer at a time.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    check_csrf();
    return (string) ($_POST['action'] ?? '');
}
