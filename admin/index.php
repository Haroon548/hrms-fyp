<?php
/*
 * Admin dashboard (FR-ADM-01, 03): today at the front desk (arrivals, in house, departures),
 * live counts, the latest bookings, and a "Needs attention" list of things that stop guests
 * booking (room types without a price, photos or rooms).
 * Revenue from paid invoices (FR-ADM-02). Recent feedback is added with feedback.
 */
require_once '../includes/init.php';
require_once '../includes/bookings.php';
require_once '../includes/admin-layout.php';
require_admin();

$counts = [];
foreach ([
    ['fa-solid fa-users', 'Customers', "SELECT COUNT(*) FROM users WHERE role = 'customer'", ''],
    ['fa-solid fa-door-open', 'Rooms', 'SELECT COUNT(*) FROM rooms', 'admin/rooms.php'],
    ['fa-solid fa-bed', 'Current & upcoming bookings', "SELECT COUNT(*) FROM bookings WHERE status = 'checked_in' OR (status = 'confirmed' AND check_out > CURDATE())", 'admin/bookings.php'],
    ['fa-solid fa-utensils', 'Food orders waiting', "SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'preparing', 'ready')", 'admin/orders.php'],
    ['fa-regular fa-calendar-check', 'Table reservations today', "SELECT COUNT(*) FROM table_reservations WHERE reservation_date = CURDATE() AND status <> 'cancelled'", 'admin/reservations.php'],
    ['fa-regular fa-envelope', 'Unread messages', 'SELECT COUNT(*) FROM contact_messages WHERE is_handled = 0', 'admin/messages.php'],
] as [$icon, $label, $sql, $link]) {
    $counts[] = [$icon, $label, (int) db()->query($sql)->fetchColumn(), $link];
}

// Today at the front desk
$today = date('Y-m-d');
$desk = [];
foreach ([
    ['fa-solid fa-plane-arrival', 'Arriving today', "status = 'confirmed' AND check_in = ?", [$today], 'arrivals'],
    ['fa-solid fa-hotel', 'Guests in house', "status = 'checked_in'", [], 'in_house'],
    ['fa-solid fa-plane-departure', 'Leaving today', "status = 'checked_in' AND check_out <= ?", [$today], 'departures'],
] as [$icon, $label, $where, $params, $view]) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM bookings WHERE $where");
    $stmt->execute($params);
    $desk[] = [$icon, $label, (int) $stmt->fetchColumn(), $view];
}

// Money from paid invoices (FR-ADM-02)
$revenue = db()->query("SELECT
    COALESCE(SUM(CASE WHEN status = 'paid' AND DATE(paid_at) = CURDATE() THEN total END), 0) AS today,
    COALESCE(SUM(CASE WHEN status = 'paid' AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN total END), 0) AS month,
    COALESCE(SUM(CASE WHEN status = 'paid' THEN total END), 0) AS total,
    COALESCE(SUM(CASE WHEN status = 'unpaid' THEN total END), 0) AS unpaid
    FROM invoices")->fetch();

// The latest bookings (FR-ADM-03)
$recent = db()->query(
    'SELECT b.id, b.reference, b.check_in, b.check_out, b.status, b.total, b.created_at, u.name AS guest_name, rt.name AS type_name
     FROM bookings b JOIN users u ON u.id = b.user_id JOIN rooms r ON r.id = b.room_id JOIN room_types rt ON rt.id = r.room_type_id
     ORDER BY b.created_at DESC, b.id DESC LIMIT 6'
)->fetchAll();

// Room types guests can see but can't book, or that look unfinished
$attention = [];
require_once '../includes/mail.php';
if (!email_is_set_up()) {
    $attention[] = ['Email isn\'t set up, so guests get no confirmation emails or sign-up codes.', 'admin/email-test.php', 'Set up email'];
}
$types = db()->query(
    "SELECT rt.id, rt.name, rt.price_per_night,
            (SELECT COUNT(*) FROM room_type_photos p WHERE p.room_type_id = rt.id) AS photos,
            (SELECT COUNT(*) FROM rooms r WHERE r.room_type_id = rt.id) AS rooms,
            (SELECT COUNT(*) FROM rooms r WHERE r.room_type_id = rt.id AND r.status = 'available') AS open_rooms
     FROM room_types rt WHERE rt.is_active = 1 ORDER BY rt.sort_order, rt.name"
)->fetchAll();
foreach ($types as $t) {
    $link = 'admin/room-type.php?id=' . $t['id'];
    if ($t['price_per_night'] === null) $attention[] = [$t['name'] . ' has no price, so guests can\'t book it.', $link, 'Set price'];
    if (!$t['photos']) $attention[] = [$t['name'] . ' has no photos.', $link . '#photos', 'Add photos'];
    if (!$t['rooms']) $attention[] = [$t['name'] . ' has no rooms, so guests can\'t book it.', 'admin/rooms.php?type=' . $t['id'], 'Add rooms'];
    elseif (!$t['open_rooms']) $attention[] = ['Every ' . $t['name'] . ' room is under maintenance.', 'admin/rooms.php?type=' . $t['id'], 'See rooms'];
}

live_watch('bookings', 'orders', 'invoices', 'table_reservations', 'users', 'rooms', 'room_types', 'room_type_photos', 'feedback'); // updates by itself when these change
admin_open('Dashboard', 'dashboard', 'Logged in as ' . current_user()['name'] . ' · ' . date('l j F Y'));
?>

<h2 class="admin-section-title">Today</h2>
<div class="desk-cards">
    <?php foreach ($desk as [$icon, $label, $number, $view]): ?>
        <a href="<?= url('admin/bookings.php?view=' . $view) ?>" class="desk-card<?= $number ? ' has-items' : '' ?>">
            <i class="<?= $icon ?>" aria-hidden="true"></i>
            <strong><?= $number ?></strong>
            <span><?= e($label) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<h2 class="admin-section-title">Revenue (paid invoices)</h2>
<div class="stat-cards stat-cards-small revenue-cards">
    <a href="<?= url('admin/invoices.php?view=paid') ?>" class="stat-card stat-card-link"><i class="fa-solid fa-sack-dollar" aria-hidden="true"></i><strong>PKR <?= money($revenue['today']) ?></strong><span>Today</span></a>
    <a href="<?= url('admin/invoices.php?view=paid') ?>" class="stat-card stat-card-link"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><strong>PKR <?= money($revenue['month']) ?></strong><span>This month</span></a>
    <a href="<?= url('admin/invoices.php?view=paid') ?>" class="stat-card stat-card-link"><i class="fa-solid fa-coins" aria-hidden="true"></i><strong>PKR <?= money($revenue['total']) ?></strong><span>All time</span></a>
    <a href="<?= url('admin/invoices.php') ?>" class="stat-card stat-card-link"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i><strong>PKR <?= money($revenue['unpaid']) ?></strong><span>Waiting to be paid</span></a>
</div>

<h2 class="admin-section-title">At a glance</h2>
<div class="stat-cards">
    <?php foreach ($counts as [$icon, $label, $number, $link]): ?>
        <?php if ($link): ?><a href="<?= url($link) ?>" class="stat-card stat-card-link"><?php else: ?><div class="stat-card"><?php endif; ?>
            <i class="<?= $icon ?>" aria-hidden="true"></i>
            <strong><?= $number ?></strong>
            <span><?= e($label) ?></span>
        <?= $link ? '</a>' : '</div>' ?>
    <?php endforeach; ?>
</div>

<section class="admin-card mt-4" aria-labelledby="recentHeading">
    <div class="confirm-details-head">
        <h2 id="recentHeading"><i class="fa-regular fa-clock" aria-hidden="true"></i> Latest bookings</h2>
        <a href="<?= url('admin/bookings.php?view=all') ?>" class="admin-link">All bookings ›</a>
    </div>
    <?php if ($recent): ?>
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead><tr><th scope="col">Booking</th><th scope="col">Guest</th><th scope="col">Room</th><th scope="col">Stay</th><th scope="col">Total</th><th scope="col">Status</th></tr></thead>
                <tbody>
                    <?php foreach ($recent as $b): ?>
                        <tr>
                            <th scope="row"><a href="<?= url('admin/booking.php?id=' . $b['id']) ?>" class="booking-ref"><?= e($b['reference']) ?></a></th>
                            <td><?= e($b['guest_name']) ?></td>
                            <td><?= e($b['type_name']) ?></td>
                            <td class="text-nowrap"><?= e(short_date($b['check_in'])) ?> → <?= e(short_date($b['check_out'])) ?></td>
                            <td class="text-nowrap">PKR <?= money($b['total']) ?></td>
                            <td><?= status_badge($b['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="admin-muted mb-0">No bookings yet. They show up here as soon as a guest books on the website.</p>
    <?php endif; ?>
</section>

<section class="admin-card mt-4" aria-labelledby="attentionHeading">
    <h2 id="attentionHeading"><i class="fa-solid fa-list-check" aria-hidden="true"></i> Needs attention</h2>
    <?php if ($attention): ?>
        <ul class="attention-list">
            <?php foreach ($attention as [$text, $link, $button]): ?>
                <li>
                    <span><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <?= e($text) ?></span>
                    <a href="<?= url($link) ?>" class="btn-small-outline"><?= e($button) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="admin-muted mb-0"><i class="fa-regular fa-circle-check" aria-hidden="true"></i> Everything guests need is in place.</p>
    <?php endif; ?>
</section>

<?php admin_close();
