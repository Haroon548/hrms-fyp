<?php
/*
 * Admin: all room bookings (FR-BOOK-08). Quick views for the front desk (arrivals today,
 * in house, departures today, upcoming, cancelled), a search box, and one-click
 * check in / check out. Details and room moves are on booking.php.
 */
require_once '../includes/init.php';
require_once '../includes/billing.php';
require_once '../includes/admin-layout.php';
require_admin();

$today = date('Y-m-d');
$views = [
    'arrivals'   => ['Arrivals today', "b.status = 'confirmed' AND b.check_in = :today", 'b.check_in, r.room_number'],
    'in_house'   => ['In house', "b.status = 'checked_in'", 'b.check_out, r.room_number'],
    'departures' => ['Departures today', "b.status = 'checked_in' AND b.check_out <= :today", 'r.room_number'],
    'upcoming'   => ['Upcoming', "b.status = 'confirmed' AND b.check_out > :today", 'b.check_in, b.id'],
    'all'        => ['All bookings', '1 = 1', 'b.created_at DESC'],
    'cancelled'  => ['Cancelled', "b.status = 'cancelled'", 'b.cancelled_at DESC'],
];
$view = isset($views[$_GET['view'] ?? '']) ? $_GET['view'] : 'upcoming';
$q = trim((string) ($_GET['q'] ?? ''));
$here = 'admin/bookings.php?' . http_build_query(array_filter(['view' => $view, 'q' => $q]));

// Check in / check out / cancel straight from the list
if (admin_action() === 'status') {
    $booking = find_booking((int) ($_POST['id'] ?? 0));
    $status = (string) ($_POST['status'] ?? '');
    if (!$booking) {
        flash('error', 'That booking no longer exists.');
    } elseif ($problem = change_booking_status($booking, $status)) {
        flash('error', $problem);
    } elseif ($status === 'checked_out') {
        $invoiceId = invoice_for_stay($booking['id']); // FR-SIG-ROOM-03
        flash('success', $booking['guest_name'] . ' has checked out. Stay invoice ' . find_invoice($invoiceId)['invoice_number'] . ' is ready in Invoices.');
    } else {
        flash('success', [
            'checked_in'  => $booking['guest_name'] . ' is checked in to room ' . $booking['room_number'] . '.',
            'checked_out' => $booking['guest_name'] . ' has checked out of room ' . $booking['room_number'] . '.',
            'cancelled'   => 'Booking ' . $booking['reference'] . ' is cancelled and the guest has been emailed.',
        ][$status]);
    }
    redirect($here);
}

// How many bookings each quick view has (shown on the tabs)
$tabCounts = [];
foreach ($views as $key => [$label, $where]) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM bookings b WHERE $where");
    $stmt->execute(strpos($where, ':today') !== false ? ['today' => $today] : []);
    $tabCounts[$key] = (int) $stmt->fetchColumn();
}

// The bookings in this view, filtered by the search box
[$label, $where, $order] = $views[$view];
$params = strpos($where, ':today') !== false ? ['today' => $today] : [];
if ($q !== '') {
    // (each placeholder only once: real prepared statements don't allow repeats)
    $where .= ' AND (b.reference LIKE :q1 OR u.name LIKE :q2 OR u.email LIKE :q3 OR u.phone LIKE :q4 OR r.room_number = :room)';
    $params += ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%", 'room' => $q];
}
$stmt = db()->prepare(
    "SELECT b.*, u.name AS guest_name, u.phone AS guest_phone, r.room_number, rt.name AS type_name
     FROM bookings b JOIN users u ON u.id = b.user_id JOIN rooms r ON r.id = b.room_id JOIN room_types rt ON rt.id = r.room_type_id
     WHERE $where ORDER BY $order LIMIT 200"
);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

live_watch('bookings', 'rooms', 'users'); // updates by itself when these change
admin_open('Bookings', 'bookings', 'Room bookings: check guests in and out, cancel, or open a booking for details',
    '<a href="' . url('admin/occupancy.php') . '" class="btn-small-outline"><i class="fa-solid fa-chart-gantt" aria-hidden="true"></i> Occupancy chart</a>');
?>

<nav class="account-tabs admin-tabs" aria-label="Booking views">
    <?php foreach ($views as $key => [$tabLabel]): ?>
        <a href="<?= url('admin/bookings.php?view=' . $key) ?>" class="<?= $key === $view ? 'active' : '' ?>"<?= $key === $view ? ' aria-current="page"' : '' ?>>
            <?= e($tabLabel) ?><?php if ($tabCounts[$key] && $key !== 'all' && $key !== 'cancelled'): ?> <span class="tab-count"><?= $tabCounts[$key] ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<form method="get" class="admin-search" role="search">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <label for="q" class="visually-hidden">Search bookings</label>
    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
    <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Booking number, guest name, phone, email or room number">
    <button type="submit" class="btn-small-red">Search</button>
    <?php if ($q !== ''): ?><a href="<?= url('admin/bookings.php?view=' . $view) ?>" class="admin-link">Clear</a><?php endif; ?>
</form>

<?php if (!$bookings): ?>
    <div class="empty-state">
        <span class="empty-icon"><i class="fa-regular fa-calendar" aria-hidden="true"></i></span>
        <h2><?= $q !== '' ? 'No bookings match "' . e($q) . '"' : 'Nothing here right now' ?></h2>
        <p><?= $q !== '' ? 'Try the booking number (e.g. RM-7K3QX9), part of the guest\'s name, or the "All bookings" tab.' : 'Bookings made on the website show up here straight away.' ?></p>
    </div>
<?php else: ?>
    <div class="table-responsive admin-table-wrap">
        <table class="table admin-table align-middle">
            <thead>
                <tr>
                    <th scope="col">Booking</th>
                    <th scope="col">Guest</th>
                    <th scope="col">Room</th>
                    <th scope="col">Stay</th>
                    <th scope="col">Total</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b):
                    $nights = booking_nights($b);
                    $moves = next_statuses($b);
                    unset($moves['cancelled']); // cancelling is on the booking page, with a reason to look first
                ?>
                    <tr>
                        <th scope="row"><a href="<?= url('admin/booking.php?id=' . $b['id']) ?>" class="booking-ref"><?= e($b['reference']) ?></a>
                            <small class="d-block admin-muted">Booked <?= e(date('j M', strtotime($b['created_at']))) ?></small></th>
                        <td><?= e($b['guest_name']) ?><small class="d-block admin-muted"><?= e($b['guest_phone']) ?></small></td>
                        <td><strong><?= e($b['room_number']) ?></strong><small class="d-block admin-muted"><?= e($b['type_name']) ?></small></td>
                        <td class="text-nowrap"><?= e(short_date($b['check_in'])) ?> → <?= e(short_date($b['check_out'])) ?>
                            <small class="d-block admin-muted"><?= $nights ?> <?= $nights === 1 ? 'night' : 'nights' ?> · <?= e(occupancy_text($b['adults'], $b['children'])) ?></small></td>
                        <td class="text-nowrap">PKR <?= money($b['total']) ?></td>
                        <td><?= status_badge($b['status']) ?>
                            <?php if ($b['status'] === 'confirmed' && $b['check_in'] < $today && $b['check_out'] > $today): ?><small class="d-block admin-warning">Not arrived yet</small><?php endif; ?>
                            <?php if ($b['status'] === 'checked_in' && $b['check_out'] < $today): ?><small class="d-block admin-warning">Should have left</small><?php endif; ?></td>
                        <td class="text-end">
                            <div class="table-actions">
                                <?php foreach ($moves as $status => $buttonLabel): ?>
                                    <form method="post"><?= csrf_field() ?>
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $status ?>">
                                        <button type="submit" class="btn-small-red text-nowrap"><?= e($buttonLabel) ?></button>
                                    </form>
                                <?php endforeach; ?>
                                <a href="<?= url('admin/booking.php?id=' . $b['id']) ?>" class="btn-icon" aria-label="Open booking <?= e($b['reference']) ?>" title="Open"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php admin_close();
