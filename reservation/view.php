<?php
/*
 * One table reservation (FR-TBL-03, 05): confirmation after reserving, and later the place
 * to see or cancel it. Only the guest who made it (or an admin) can open it.
 *   reservation/view.php?id=12
 */
require_once '../includes/init.php';
require_once '../includes/reservations.php';
require_login();

$r = find_reservation((int) ($_GET['id'] ?? 0));
$mine = $r && (int) $r['user_id'] === (int) current_user()['id'];
if (!$r || (!$mine && !is_admin())) {
    http_response_code(404);
    $page_title = 'Reservation not found | ' . HOTEL_NAME;
    include '../includes/header.php';
    ?>
    <section class="section"><div class="container"><div class="empty-state">
        <span class="empty-icon"><i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i></span>
        <h1 class="h2">We couldn't find that reservation</h1>
        <p>All your reservations are in My Account.</p>
        <a href="<?= url('account/index.php?tab=reservations') ?>" class="btn-primary-red">My reservations</a>
    </div></div></section>
    <?php
    include '../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['action'] ?? '') === 'cancel' && $mine) {
        if (!can_cancel_reservation($r)) {
            flash('error', 'This reservation can\'t be cancelled any more.');
        } elseif ($problem = change_reservation_status($r, 'cancelled')) {
            flash('error', $problem);
        } else {
            flash('success', 'Your reservation is cancelled. We hope to see you another time.');
        }
    }
    redirect('reservation/view.php?id=' . $r['id']);
}

$isNew = isset($_GET['new']);
$when = date('l j F Y', strtotime($r['reservation_date'])) . ', ' . nice_time($r['reservation_time']);
$directions = 'https://www.google.com/maps/search/?api=1&query=' . urlencode(HOTEL_NAME . ', ' . HOTEL_ADDRESS);
$share = 'Table for ' . $r['party_size'] . ' at ' . $r['restaurant'] . ', ' . HOTEL_NAME . ': ' . $when . '. ' . HOTEL_ADDRESS;

$page_title = 'Reservation ' . reservation_number($r) . ' | ' . HOTEL_NAME;
$active_page = 'dining';
$flash_inline = true;
live_watch('table_reservations', 'dining_tables');
include '../includes/header.php';
?>
<div data-live-region="hero">
<section class="confirm-hero<?= $r['status'] === 'cancelled' ? ' is-cancelled' : '' ?>">
    <div class="container">
        <span class="confirm-icon<?= $isNew ? ' is-pop' : '' ?>" aria-hidden="true"><i class="fa-solid <?= $r['status'] === 'cancelled' ? 'fa-ban' : 'fa-champagne-glasses' ?>"></i></span>
        <h1><?= $r['status'] === 'cancelled' ? 'This reservation is cancelled' : ($isNew ? 'Your table is reserved!' : 'Table reservation') ?></h1>
        <p><?= e($r['restaurant']) ?> · <?= e($when) ?> · <?= (int) $r['party_size'] ?> <?= $r['party_size'] == 1 ? 'guest' : 'guests' ?></p>
    </div>
</section>
</div>

<section class="section confirm-section order-page">
    <div class="container">
        <?php show_flash(); ?>
        <div class="row g-4" data-live-region="details">
            <div class="col-lg-7">
                <div class="account-card">
                    <div class="confirm-details-head"><h2>Reservation details</h2><?= status_badge($r['status']) ?></div>
                    <dl class="detail-list">
                        <div><dt>Reservation</dt><dd><strong><?= e(reservation_number($r)) ?></strong></dd></div>
                        <div><dt>Restaurant</dt><dd><?= e($r['restaurant']) ?></dd></div>
                        <div><dt>When</dt><dd><?= e($when) ?></dd></div>
                        <div><dt>Guests</dt><dd><?= (int) $r['party_size'] ?></dd></div>
                        <div><dt>Table</dt><dd>Table <?= e($r['table_number']) ?> (<?= (int) $r['seats'] ?> seats)</dd></div>
                        <div><dt>Name</dt><dd><?= e($r['guest_name']) ?> · <?= e($r['guest_phone']) ?></dd></div>
                        <?php if ($r['notes'] !== ''): ?><div><dt>Your note</dt><dd><?= e($r['notes']) ?></dd></div><?php endif; ?>
                    </dl>
                    <?php if ($r['status'] === 'confirmed'): ?>
                        <p class="confirm-cancel"><i class="fa-regular fa-clock" aria-hidden="true"></i> We hold your table for 15 minutes after the reserved time.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="account-card">
                    <h2>Before you come</h2>
                    <div class="quick-actions">
                        <a href="<?= url('dining.php#menu') ?>"><i class="fa-solid fa-book-open" aria-hidden="true"></i> See the menu</a>
                        <a href="<?= e($directions) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Get directions</a>
                        <a href="https://wa.me/?text=<?= e(urlencode($share)) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Share with your guests</a>
                        <?php if ($mine): ?><a href="<?= url('account/index.php?tab=reservations') ?>"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i> All my reservations</a><?php endif; ?>
                    </div>
                    <?php if ($mine && can_cancel_reservation($r)): ?>
                        <form method="post" class="mt-3" data-confirm="Cancel your table at <?= e($r['restaurant']) ?> on <?= e(date('j M', strtotime($r['reservation_date']))) ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn-small-outline w-100">Cancel reservation</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include '../includes/footer.php';
