<?php
/*
 * My Account (design.md: My Account). Tabs: bookings (FR-BOOK-06, cancel: FR-BOOK-07),
 * orders (FR-ORD-04), reservations (FR-TBL-05), invoices (FR-BILL-05) and profile, where guests
 * change their details and password (FR-AUTH-07). Changing the password takes the current one,
 * then a 6-digit code emailed to the account, like a bank; the new password is only saved
 * after the right code, and a "your password was changed" alert is emailed (FR-AUTH-13).
 * Changing the email works the same way: the code goes to the NEW address (proving it's
 * theirs), and the OLD address gets a "your email was changed" alert (FR-AUTH-14).
 */
require_once '../includes/init.php';
require_once '../includes/billing.php';
require_once '../includes/reservations.php';
require_once '../includes/mail.php';
require_login();

$user = current_user();
$tabs = [
    'bookings'     => ['fa-solid fa-bed', 'My Bookings'],
    'orders'       => ['fa-solid fa-utensils', 'My Orders'],
    'reservations' => ['fa-regular fa-calendar-check', 'My Reservations'],
    'invoices'     => ['fa-solid fa-file-invoice', 'My Invoices'],
    'profile'      => ['fa-regular fa-user', 'Profile'],
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'bookings';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $tab = 'profile';
    $action = $_POST['action'] ?? '';

    // Cancel an upcoming booking (FR-BOOK-07): only the guest's own, and only until 6 PM on arrival day
    if ($action === 'cancel_booking') {
        $booking = find_booking((string) ($_POST['reference'] ?? ''));
        if (!$booking || (int) $booking['user_id'] !== (int) $user['id']) {
            flash('error', 'That booking wasn\'t found.');
        } elseif (!can_cancel($booking)) {
            flash('error', 'Booking ' . $booking['reference'] . ' can\'t be cancelled online any more. Please call the hotel on ' . HOTEL_PHONE . '.');
        } elseif ($problem = change_booking_status($booking, 'cancelled')) {
            flash('error', $problem);
        } else {
            flash('success', 'Booking ' . $booking['reference'] . ' has been cancelled. Nothing is charged, and we\'ve emailed you a confirmation.');
        }
        redirect('account/index.php?tab=bookings');
    }

    if ($action === 'details') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Enter your full name.';
        }
        if (!preg_match('/^\+?[0-9 \-]{7,20}$/', $phone)) {
            $errors['phone'] = 'Enter a phone number, e.g. 0300 1234567.';
        }
        if (!$errors) {
            db()->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')->execute([$name, $phone, $user['id']]);
            flash('success', 'Your details have been saved.');
            redirect('account/index.php?tab=profile');
        }
    }

    if ($action === 'password') {
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $new = $_POST['password'] ?? '';
        if (!password_verify($_POST['current_password'] ?? '', $stmt->fetchColumn())) {
            $errors['current_password'] = 'Your current password is wrong.';
        } elseif ($problem = password_problem($new)) {
            $errors['password'] = $problem;
        } elseif ($new !== ($_POST['password_confirm'] ?? '')) {
            $errors['password_confirm'] = 'The passwords don\'t match.';
        }
        if (!$errors) {
            // Step 2: confirm with a code sent to the account's email (the new password waits in the session, hashed)
            $_SESSION['pending_password'] = password_hash($new, PASSWORD_DEFAULT);
            if (code_wait_seconds($user['id'], 'password') === 0) {
                send_code_email($user, create_email_code($user['id'], 'password'), 'password');
            }
            flash('info', 'To make sure it\'s you, we\'ve emailed a 6-digit code to ' . masked_email($user['email']) . '. Enter it below to finish.');
            redirect('account/index.php?tab=profile#password');
        }
    }

    if ($action === 'password_code' && !empty($_SESSION['pending_password'])) {
        $codeProblem = check_email_code($user['id'], 'password', $_POST['code'] ?? '');
        if ($codeProblem !== '') {
            $errors['code'] = $codeProblem;
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$_SESSION['pending_password'], $user['id']]);
            unset($_SESSION['pending_password']);
            session_regenerate_id(true);
            send_password_changed_email($user);
            flash('success', 'Your password has been changed. We\'ve emailed you a note about it.');
            redirect('account/index.php?tab=profile');
        }
    }

    if ($action === 'resend' && !empty($_SESSION['pending_password'])) {
        if (code_wait_seconds($user['id'], 'password') === 0) {
            send_code_email($user, create_email_code($user['id'], 'password'), 'password');
            flash('success', 'A new code is on its way to ' . masked_email($user['email']) . '.');
        }
        redirect('account/index.php?tab=profile#password');
    }

    if ($action === 'email_change') {
        $newEmail = strtolower(trim($_POST['new_email'] ?? ''));
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 191) {
            $errors['new_email'] = 'Enter the new email address.';
        } elseif ($newEmail === strtolower($user['email'])) {
            $errors['new_email'] = 'That is already your email.';
        } elseif (!password_verify($_POST['email_password'] ?? '', $stmt->fetchColumn())) {
            $errors['email_password'] = 'Your password is wrong.';
        } else {
            $taken = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $taken->execute([$newEmail, $user['id']]);
            if ($taken->fetchColumn() > 0) {
                $errors['new_email'] = 'Another account already uses this email.';
            }
        }
        if (!$errors) {
            $_SESSION['pending_email'] = $newEmail;
            if (code_wait_seconds($user['id'], 'email') === 0) {
                send_code_email(['name' => $user['name'], 'email' => $newEmail], create_email_code($user['id'], 'email'), 'email');
            }
            flash('info', 'We\'ve emailed a 6-digit code to ' . $newEmail . '. Enter it below to switch your account to that address.');
            redirect('account/index.php?tab=profile#email');
        }
    }

    if ($action === 'email_code' && !empty($_SESSION['pending_email'])) {
        $newEmail = $_SESSION['pending_email'];
        $codeProblem = check_email_code($user['id'], 'email', $_POST['code'] ?? '');
        $taken = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
        $taken->execute([$newEmail, $user['id']]);
        if ($codeProblem !== '') {
            $errors['code'] = $codeProblem;
        } elseif ($taken->fetchColumn() > 0) {
            unset($_SESSION['pending_email']); // someone signed up with it meanwhile
            flash('error', 'Another account started using ' . $newEmail . ' meanwhile, so your email stays the same.');
            redirect('account/index.php?tab=profile');
        } else {
            $oldEmail = $user['email'];
            db()->prepare('UPDATE users SET email = ?, email_verified_at = NOW() WHERE id = ?')->execute([$newEmail, $user['id']]);
            unset($_SESSION['pending_email']);
            send_email_changed_email(['name' => $user['name'], 'email' => $newEmail], $oldEmail);
            flash('success', 'Your email is now ' . $newEmail . '. Use it to log in from now on.');
            redirect('account/index.php?tab=profile');
        }
    }

    if ($action === 'email_resend' && !empty($_SESSION['pending_email'])) {
        if (code_wait_seconds($user['id'], 'email') === 0) {
            send_code_email(['name' => $user['name'], 'email' => $_SESSION['pending_email']], create_email_code($user['id'], 'email'), 'email');
            flash('success', 'A new code is on its way to ' . $_SESSION['pending_email'] . '.');
        }
        redirect('account/index.php?tab=profile#email');
    }

    if ($action === 'email_cancel') {
        unset($_SESSION['pending_email']);
        redirect('account/index.php?tab=profile');
    }

    if ($action === 'password_cancel') {
        unset($_SESSION['pending_password']);
        redirect('account/index.php?tab=profile');
    }
}

// How many of each the guest has (the lists themselves come with each feature's chunk)
$counts = [];
foreach (['bookings' => 'bookings', 'orders' => 'orders', 'reservations' => 'table_reservations', 'invoices' => 'invoices'] as $key => $table) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM $table WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $counts[$key] = (int) $stmt->fetchColumn();
}

// The guest's stays: upcoming first (soonest at the top), then past and cancelled (newest first)
$bookings = ['upcoming' => [], 'past' => []];
if ($tab === 'bookings') {
    $stmt = db()->prepare('SELECT reference FROM bookings WHERE user_id = ? ORDER BY check_in');
    $stmt->execute([$user['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $reference) {
        $b = find_booking($reference);
        $isUpcoming = in_array($b['status'], ['confirmed', 'checked_in'], true) && $b['check_out'] >= date('Y-m-d');
        $bookings[$isUpcoming ? 'upcoming' : 'past'][] = $b;
    }
    $bookings['past'] = array_reverse($bookings['past']);
}

// The guest's food orders, newest first, with their dishes
$orders = [];
if ($tab === 'orders') {
    $stmt = db()->prepare('SELECT id FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$user['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $orderId) {
        $orders[] = find_order($orderId);
    }
}

// The guest's table reservations: upcoming first, then past and cancelled
$reservations = ['upcoming' => [], 'past' => []];
if ($tab === 'reservations') {
    $stmt = db()->prepare('SELECT id FROM table_reservations WHERE user_id = ? ORDER BY reservation_date, reservation_time');
    $stmt->execute([$user['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $reservationId) {
        $r = find_reservation($reservationId);
        $upcoming = $r['status'] === 'confirmed' && strtotime($r['reservation_date'] . ' ' . $r['reservation_time']) > time() - 3 * 3600;
        $reservations[$upcoming ? 'upcoming' : 'past'][] = $r;
    }
    $reservations['past'] = array_reverse($reservations['past']);
}

// While the guest is checked in: their stay and running room bill (FR-SIG-ROOM-02)
$stayNow = current_stay($user['id']);
if ($stayNow) {
    $booking = find_booking((int) $stayNow['id']);
    $bill = running_bill($booking);
}

// The guest's invoices, newest first (FR-BILL-05)
$invoices = [];
if ($tab === 'invoices') {
    $stmt = db()->prepare('SELECT id FROM invoices WHERE user_id = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$user['id']]);
    $invoices = array_map('find_invoice', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$empty = [
    'bookings'     => ['fa-solid fa-bed', 'No bookings yet', 'When you book a room, it shows up here with its dates, status and invoice.', 'rooms.php', 'Book a room'],
    'orders'       => ['fa-solid fa-utensils', 'No food orders yet', 'Order from Buzz 76 or Cafe Saints for your table, for takeaway, or to your room.', 'dining.php#menu', 'See the menu'],
    'reservations' => ['fa-regular fa-calendar-check', 'No table reservations yet', 'Reserve a table at one of our restaurants for any date and time.', 'reserve-table.php', 'Reserve a table'],
    'invoices'     => ['fa-solid fa-file-invoice', 'No invoices yet', 'Invoices for your stays and orders appear here, ready to view and print.', 'rooms.php', 'Book a room'],
];

$page_title = 'My Account | ' . HOTEL_NAME;
$page_scripts = ['assets/js/auth.js'];
$flash_inline = true;
if ($tab !== 'profile') {
    // Lists change by themselves, e.g. when the hotel checks the guest in or the kitchen readies an order
    live_watch('bookings', 'orders', 'table_reservations', 'invoices', 'rooms', 'feedback');
}
include '../includes/header.php';
require_once '../includes/auth-layout.php';
?>
<section class="account-hero">
    <div class="container">
        <p class="eyebrow">My Account</p>
        <h1>Hello, <?= e(explode(' ', trim($user['name']))[0]) ?></h1>
        <p><?= e($user['email']) ?><?php if ($user['email_verified_at']): ?> <span class="verified-chip"><i class="fa-solid fa-circle-check"></i> Confirmed</span><?php endif; ?></p>
    </div>
</section>

<section class="section account-section">
    <div class="container">
        <?php show_flash(); ?>
        <div data-live-region="account">
        <?php if ($stayNow): ?>
            <section class="current-stay" aria-labelledby="stayHeading">
                <div class="current-stay-head">
                    <div>
                        <p class="eyebrow">Your current stay</p>
                        <h2 id="stayHeading">Room <?= e($booking['room_number']) ?> · <?= e($booking['type_name']) ?></h2>
                        <p><?= e(short_date($booking['check_in'])) ?> → <?= e(short_date($booking['check_out'])) ?> · check-out by <?= CHECK_OUT_TIME ?></p>
                    </div>
                    <a href="<?= url('dining.php#menu') ?>" class="btn-primary-red"><i class="fa-solid fa-bell-concierge" aria-hidden="true"></i> ORDER ROOM SERVICE</a>
                </div>
                <?php include '../includes/bill-lines.php'; ?>
                <p class="current-stay-note">Room service is added to this bill. You pay everything once, at check-out.</p>
            </section>
        <?php endif; ?>
        <nav class="account-tabs" aria-label="Account sections">
            <?php foreach ($tabs as $key => [$icon, $label]): ?>
                <a href="<?= url('account/index.php?tab=' . $key) ?>" class="<?= $key === $tab ? 'active' : '' ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>>
                    <i class="<?= $icon ?>" aria-hidden="true"></i> <?= e($label) ?>
                    <?php if (isset($counts[$key]) && $counts[$key] > 0): ?><span class="tab-count"><?= $counts[$key] ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="account-body">
            <?php if ($tab === 'profile'): ?>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <form method="post" class="account-card account-card-fit" novalidate>
                            <h2>Your details</h2>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="details">
                            <?php text_input('name', 'Full name', 'text', 'name', old('name', $user['name']), $errors['name'] ?? '', 'maxlength="100"'); ?>
                            <?php text_input('phone', 'Phone', 'tel', 'tel', old('phone', $user['phone']), $errors['phone'] ?? '', 'maxlength="20"'); ?>
                            <button type="submit" class="btn-primary-red">SAVE DETAILS</button>
                        </form>

                        <div class="account-card account-card-fit mt-4" id="email">
                            <h2>Email address</h2>
                            <?php if (!empty($_SESSION['pending_email'])): ?>
                                <p>We've emailed a 6-digit code to <strong><?= e($_SESSION['pending_email']) ?></strong>. Enter it to switch your account to that address.</p>
                                <form method="post" novalidate data-code-form>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="email_code">
                                    <?php code_input($errors['code'] ?? ''); ?>
                                    <button type="submit" class="btn-primary-red">CONFIRM NEW EMAIL</button>
                                </form>
                                <?php resend_code_button(code_wait_seconds($user['id'], 'email'), 'email_resend'); ?>
                                <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="email_cancel">
                                    <button type="submit" class="link-button">Cancel: keep <?= e($user['email']) ?></button></form>
                            <?php else: ?>
                                <p>You log in and get codes and confirmations at <strong><?= e($user['email']) ?></strong>.</p>
                                <details class="change-email"<?= isset($errors['new_email']) || isset($errors['email_password']) ? ' open' : '' ?>>
                                    <summary class="btn-small-outline">Change email</summary>
                                    <form method="post" novalidate class="mt-3">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="email_change">
                                        <?php text_input('new_email', 'New email', 'email', 'email', old('new_email'), $errors['new_email'] ?? '', 'maxlength="191"'); ?>
                                        <?php password_input('email_password', 'Your password', 'current-password', false, $errors['email_password'] ?? ''); ?>
                                        <p class="field-help">We'll email a code to the new address to make sure it's yours.</p>
                                        <button type="submit" class="btn-primary-red">SEND CODE</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <?php if (!empty($_SESSION['pending_password'])): ?>
                            <div class="account-card" id="password">
                                <h2>Confirm it's you</h2>
                                <p>We've emailed a 6-digit code to <strong><?= e(masked_email($user['email'])) ?></strong>. Enter it to save your new password.</p>
                                <form method="post" novalidate data-code-form>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="password_code">
                                    <?php code_input($errors['code'] ?? ''); ?>
                                    <button type="submit" class="btn-primary-red">SAVE NEW PASSWORD</button>
                                </form>
                                <?php resend_code_button(code_wait_seconds($user['id'], 'password')); ?>
                                <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="password_cancel">
                                    <button type="submit" class="link-button">Cancel: keep my current password</button></form>
                            </div>
                        <?php else: ?>
                        <form method="post" class="account-card" id="password" novalidate>
                            <h2>Change password</h2>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="password">
                            <?php password_input('current_password', 'Current password', 'current-password', false, $errors['current_password'] ?? ''); ?>
                            <?php password_input('password', 'New password', 'new-password', true, $errors['password'] ?? ''); ?>
                            <?php password_input('password_confirm', 'Confirm new password', 'new-password', false, $errors['password_confirm'] ?? ''); ?>
                            <p class="field-help">For your safety we'll email a code to <?= e(masked_email($user['email'])) ?> to confirm the change.</p>
                            <button type="submit" class="btn-primary-red">CHANGE PASSWORD</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($tab === 'invoices' && $invoices): ?>
                <div class="stay-list">
                    <?php foreach ($invoices as $inv): ?>
                        <article class="order-row">
                            <span class="order-row-icon" aria-hidden="true"><i class="fa-solid fa-file-invoice"></i></span>
                            <div class="stay-row-main">
                                <p class="stay-row-ref"><?= e($inv['invoice_number']) ?> <?= status_badge($inv['status']) ?></p>
                                <h3><a href="<?= url('invoice/view.php?id=' . $inv['id']) ?>"><?= e(invoice_subject($inv)) ?></a></h3>
                                <p class="stay-row-note"><?= e(date('j M Y', strtotime($inv['created_at']))) ?> · <?= count($inv['items']) ?> <?= count($inv['items']) === 1 ? 'line' : 'lines' ?></p>
                            </div>
                            <div class="stay-row-side">
                                <p class="stay-row-total">PKR <?= money($inv['total']) ?><small><?= $inv['status'] === 'paid' ? 'Paid ' . e(date('j M', strtotime($inv['paid_at']))) : 'Pay at the hotel' ?></small></p>
                                <div class="stay-row-buttons"><a href="<?= url('invoice/view.php?id=' . $inv['id']) ?>" class="btn-small-red"><i class="fa-solid fa-print" aria-hidden="true"></i> View / print</a></div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($tab === 'reservations' && $counts['reservations'] > 0): ?>
                <?php foreach (['upcoming' => 'Coming up', 'past' => 'Past and cancelled'] as $group => $heading): ?>
                    <?php if (!$reservations[$group]) continue; ?>
                    <h2 class="account-group"><?= e($heading) ?></h2>
                    <div class="stay-list">
                        <?php foreach ($reservations[$group] as $r): ?>
                            <article class="order-row<?= $r['status'] === 'cancelled' ? ' is-cancelled' : '' ?>">
                                <span class="date-tile" aria-hidden="true"><b><?= date('j', strtotime($r['reservation_date'])) ?></b><?= date('M', strtotime($r['reservation_date'])) ?></span>
                                <div class="stay-row-main">
                                    <p class="stay-row-ref"><?= e(reservation_number($r)) ?> <?= status_badge($r['status']) ?></p>
                                    <h3><a href="<?= url('reservation/view.php?id=' . $r['id']) ?>"><?= e($r['restaurant']) ?></a></h3>
                                    <p class="order-row-items"><?= e(date('l', strtotime($r['reservation_date']))) ?>, <?= e(nice_time($r['reservation_time'])) ?> · <?= (int) $r['party_size'] ?> <?= $r['party_size'] == 1 ? 'guest' : 'guests' ?> · Table <?= e($r['table_number']) ?></p>
                                </div>
                                <div class="stay-row-side">
                                    <div class="stay-row-buttons">
                                        <a href="<?= url('reservation/view.php?id=' . $r['id']) ?>" class="btn-small-red">View</a>
                                        <?php if (can_cancel_reservation($r)): ?>
                                            <form method="post" action="<?= url('reservation/view.php?id=' . $r['id']) ?>" data-confirm="Cancel your table at <?= e($r['restaurant']) ?>?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn-small-outline">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <p class="account-more"><a href="<?= url('reserve-table.php') ?>" class="text-link">Reserve another table</a></p>
            <?php elseif ($tab === 'orders' && $orders): ?>
                <div class="stay-list">
                    <?php foreach ($orders as $o): $typeIcon = ORDER_TYPES[$o['order_type']][1]; ?>
                        <article class="order-row<?= $o['status'] === 'cancelled' ? ' is-cancelled' : '' ?>">
                            <span class="order-row-icon" aria-hidden="true"><i class="<?= $typeIcon ?>"></i></span>
                            <div class="stay-row-main">
                                <p class="stay-row-ref">Order <?= e(order_number($o)) ?> <?= status_badge($o['status']) ?></p>
                                <h3><a href="<?= url('order/view.php?id=' . $o['id']) ?>"><?= e(order_heading($o)) ?></a></h3>
                                <p class="order-row-items"><?= e(order_summary($o)) ?></p>
                                <p class="stay-row-note"><?= e(date('D j M Y, g:i A', strtotime($o['created_at']))) ?></p>
                            </div>
                            <div class="stay-row-side">
                                <p class="stay-row-total">PKR <?= money($o['total']) ?><small><?= $o['order_type'] === 'room_service' ? 'On your room bill' : ($o['status'] === 'cancelled' ? 'Not charged' : 'Pay at the restaurant') ?></small></p>
                                <div class="stay-row-buttons">
                                    <a href="<?= url('order/view.php?id=' . $o['id']) ?>" class="btn-small-red"><?= in_array($o['status'], ['pending', 'preparing', 'ready'], true) ? 'Follow' : 'View' ?></a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="account-more"><a href="<?= url('dining.php#menu') ?>" class="text-link">Order from the menu</a></p>
            <?php elseif ($tab === 'bookings' && $counts['bookings'] > 0): ?>
                <?php foreach (['upcoming' => 'Upcoming stays', 'past' => 'Past and cancelled'] as $group => $heading): ?>
                    <?php if (!$bookings[$group]) continue; ?>
                    <h2 class="account-group"><?= e($heading) ?></h2>
                    <div class="stay-list">
                        <?php foreach ($bookings[$group] as $b): $n = booking_nights($b); ?>
                            <article class="stay-row<?= $b['status'] === 'cancelled' ? ' is-cancelled' : '' ?>">
                                <a href="<?= url('booking/confirmation.php?ref=' . urlencode($b['reference'])) ?>" class="stay-row-photo" tabindex="-1" aria-hidden="true">
                                    <?php photo($b['photo'] ?? '', $b['type_name']); ?>
                                </a>
                                <div class="stay-row-main">
                                    <p class="stay-row-ref"><?= e($b['reference']) ?> <?= status_badge($b['status']) ?></p>
                                    <h3><a href="<?= url('booking/confirmation.php?ref=' . urlencode($b['reference'])) ?>"><?= e($b['type_name']) ?></a></h3>
                                    <ul class="room-facts">
                                        <li><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= e(short_date($b['check_in'])) ?> → <?= e(short_date($b['check_out'])) ?> · <?= $n ?> <?= $n === 1 ? 'night' : 'nights' ?></li>
                                        <li><i class="fa-solid fa-user-group" aria-hidden="true"></i> <?= e(occupancy_text($b['adults'], $b['children'])) ?></li>
                                        <?php if ($b['status'] === 'checked_in'): ?><li><i class="fa-solid fa-door-open" aria-hidden="true"></i> Room <?= e($b['room_number']) ?></li><?php endif; ?>
                                    </ul>
                                    <?php if ($b['status'] === 'confirmed'): ?>
                                        <p class="stay-row-note"><?= can_cancel($b)
                                            ? 'Free cancellation until 6 PM on ' . e(short_date($b['check_in']))
                                            : 'Free cancellation has ended. Call ' . e(HOTEL_PHONE) . ' to change this stay.' ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="stay-row-side">
                                    <p class="stay-row-total">PKR <?= money($b['total']) ?><small><?= $b['status'] === 'cancelled' ? 'Not charged' : 'Pay at the hotel' ?></small></p>
                                    <div class="stay-row-buttons">
                                        <a href="<?= url('booking/confirmation.php?ref=' . urlencode($b['reference'])) ?>" class="btn-small-red">View</a>
                                        <?php if ($b['status'] === 'confirmed'): ?>
                                            <a href="<?= url('booking/calendar-file.php?ref=' . urlencode($b['reference'])) ?>" class="btn-small-outline" title="Add to my calendar"><i class="fa-regular fa-calendar-plus" aria-hidden="true"></i><span class="visually-hidden">Add to my calendar</span></a>
                                        <?php endif; ?>
                                        <?php if (can_cancel($b)): ?>
                                            <form method="post" data-confirm="Cancel booking <?= e($b['reference']) ?> (<?= e($b['type_name']) ?>, <?= e(short_date($b['check_in'])) ?>)? This can't be undone.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="cancel_booking">
                                                <input type="hidden" name="reference" value="<?= e($b['reference']) ?>">
                                                <button type="submit" class="btn-small-outline">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <p class="account-more"><a href="<?= url('rooms.php') ?>" class="text-link">Book another stay</a></p>
            <?php else: ?>
                <?php [$icon, $title, $text, $link, $button] = $empty[$tab]; ?>
                <div class="empty-state">
                    <span class="empty-icon"><i class="<?= $icon ?>" aria-hidden="true"></i></span>
                    <h2><?= e($title) ?></h2>
                    <p><?= e($text) ?></p>
                    <a href="<?= url($link) ?>" class="btn-primary-red"><?= e($button) ?></a>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </div>
</section>
<?php include '../includes/footer.php';
