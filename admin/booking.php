<?php
/*
 * Admin: one booking (FR-BOOK-08). Guest details, stay, price, special requests,
 * status buttons (check in, check out, cancel), and moving the guest to another free
 * room, e.g. when their room needs repairs.
 *   admin/booking.php?id=12
 */
require_once '../includes/init.php';
require_once '../includes/billing.php';
require_once '../includes/admin-layout.php';
require_admin();

$booking = find_booking((int) ($_GET['id'] ?? 0));
if (!$booking) {
    flash('error', 'That booking doesn\'t exist.');
    redirect('admin/bookings.php');
}
$here = 'admin/booking.php?id=' . $booking['id'];

switch (admin_action()) {
    case 'status':
        $status = (string) ($_POST['status'] ?? '');
        if ($problem = change_booking_status($booking, $status)) {
            flash('error', $problem);
        } elseif ($status === 'checked_out') {
            // One invoice for the whole stay: room nights + room service (FR-SIG-ROOM-03)
            $invoiceId = invoice_for_stay($booking['id']);
            flash('success', $booking['guest_name'] . ' has checked out. Here is the stay invoice to print and mark paid.');
            redirect('invoice/view.php?id=' . $invoiceId);
        } else {
            flash('success', [
                'checked_in'  => $booking['guest_name'] . ' is checked in to room ' . $booking['room_number'] . '.',
                'checked_out' => $booking['guest_name'] . ' has checked out.',
                'cancelled'   => 'The booking is cancelled and ' . $booking['guest_name'] . ' has been emailed.',
            ][$status]);
        }
        redirect($here);

    case 'invoice':
        // For stays checked out before billing existed, or if making it failed at check-out
        if ($booking['status'] === 'checked_out') {
            redirect('invoice/view.php?id=' . invoice_for_stay($booking['id']));
        }
        redirect($here);

    case 'move':
        // Only to a room that is free for the whole stay (checked again here, not just in the list)
        $roomId = (int) ($_POST['room_id'] ?? 0);
        $allowed = array_column(rooms_to_move_to($booking), 'room_number', 'id');
        if (!in_array($booking['status'], ['confirmed', 'checked_in'], true)) {
            flash('error', 'Only upcoming or current stays can be moved.');
        } elseif (!isset($allowed[$roomId])) {
            flash('error', 'That room isn\'t free for these dates any more. Choose another.');
        } else {
            db()->prepare('UPDATE bookings SET room_id = ? WHERE id = ?')->execute([$roomId, $booking['id']]);
            flash('success', 'Moved from room ' . $booking['room_number'] . ' to room ' . $allowed[$roomId] . '. The price stays the same.');
        }
        redirect($here);
}

$nights = booking_nights($booking);
$moves = next_statuses($booking);
$roomStatus = db()->prepare('SELECT status FROM rooms WHERE id = ?');
$roomStatus->execute([$booking['room_id']]);
$roomInMaintenance = $roomStatus->fetchColumn() === 'maintenance';
$bill = $booking['status'] === 'checked_in' ? running_bill($booking) : null;
$stmt = db()->prepare('SELECT id, invoice_number, status FROM invoices WHERE booking_id = ?');
$stmt->execute([$booking['id']]);
$invoice = $stmt->fetch();
$moveRooms = in_array($booking['status'], ['confirmed', 'checked_in'], true) ? rooms_to_move_to($booking) : [];
$today = date('Y-m-d');

live_watch('bookings', 'rooms', 'orders', 'invoices', 'users'); // updates by itself when these change
admin_open('Booking ' . $booking['reference'], 'bookings', $booking['guest_name'] . ' · ' . $booking['type_name'] . ' · room ' . $booking['room_number']);
?>

<nav class="crumbs crumbs-dark admin-crumbs" aria-label="You are here">
    <a href="<?= url('admin/bookings.php') ?>">Bookings</a> <span aria-hidden="true">/</span> <?= e($booking['reference']) ?>
</nav>

<div class="row g-4">
    <div class="col-xl-8">
        <section class="admin-card">
            <div class="confirm-details-head">
                <h2>Stay</h2>
                <?= status_badge($booking['status']) ?>
            </div>
            <dl class="detail-list">
                <div><dt>Room</dt><dd><strong><?= e($booking['room_number']) ?></strong> · <?= e($booking['type_name']) ?></dd></div>
                <div><dt>Check-in</dt><dd><?= e(long_date($booking['check_in'])) ?><?= $booking['arrival_time'] !== '' ? ' · arriving ' . e($booking['arrival_time']) : '' ?></dd></div>
                <div><dt>Check-out</dt><dd><?= e(long_date($booking['check_out'])) ?></dd></div>
                <div><dt>Nights</dt><dd><?= $nights ?></dd></div>
                <div><dt>Guests</dt><dd><?= e(occupancy_text($booking['adults'], $booking['children'])) ?></dd></div>
                <div><dt>Special requests</dt><dd><?= $booking['special_requests'] ? nl2br(e($booking['special_requests'])) : '<span class="admin-muted">None</span>' ?></dd></div>
                <div><dt>Price</dt><dd>PKR <?= money($booking['price_per_night']) ?> × <?= $nights ?> (price when booked)</dd></div>
                <div class="detail-total"><dt>Total</dt><dd>PKR <?= money($booking['total']) ?> <small>Pay at the hotel</small></dd></div>
                <div><dt>Booked on</dt><dd><?= e(date('D j M Y, g:i A', strtotime($booking['created_at']))) ?></dd></div>
                <?php if ($booking['cancelled_at']): ?>
                    <div><dt>Cancelled on</dt><dd><?= e(date('D j M Y, g:i A', strtotime($booking['cancelled_at']))) ?></dd></div>
                <?php endif; ?>
            </dl>
        </section>

        <?php if ($bill): ?>
            <section class="admin-card" aria-labelledby="billHeading">
                <h2 id="billHeading"><i class="fa-solid fa-receipt" aria-hidden="true"></i> Room bill so far</h2>
                <?php include '../includes/bill-lines.php'; ?>
                <p class="admin-muted mb-0">At check-out one invoice is made with the full stay (PKR <?= money($bill['full_stay']) ?>) and every room service order.</p>
            </section>
        <?php endif; ?>

        <?php if ($moveRooms): ?>
            <section class="admin-card" aria-labelledby="moveHeading">
                <h2 id="moveHeading"><i class="fa-solid fa-right-left" aria-hidden="true"></i> Move to another room</h2>
                <p class="admin-muted">These rooms are free for the whole stay. The guest keeps the price they booked.</p>
                <form method="post" class="admin-move" data-confirm="Move <?= e($booking['guest_name']) ?> to the chosen room?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="move">
                    <div class="form-field">
                        <label for="room_id">New room</label>
                        <select id="room_id" name="room_id" required>
                            <?php $group = null; foreach ($moveRooms as $room): ?>
                                <?php if ($group !== $room['type_name']): ?>
                                    <?= $group !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($room['type_name']) ?>">
                                    <?php $group = $room['type_name']; ?>
                                <?php endif; ?>
                                <option value="<?= $room['id'] ?>">Room <?= e($room['room_number']) ?></option>
                            <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary-red">Move guest</button>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <div class="col-xl-4">
        <section class="admin-card admin-card-highlight">
            <h2>Guest</h2>
            <p class="guest-name"><?= e($booking['guest_name']) ?></p>
            <div class="quick-actions">
                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $booking['guest_phone'])) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> <?= e($booking['guest_phone']) ?></a>
                <a href="mailto:<?= e($booking['guest_email']) ?>"><i class="fa-regular fa-envelope" aria-hidden="true"></i> <?= e($booking['guest_email']) ?></a>
                <?php $wa = preg_replace('/\D/', '', $booking['guest_phone']); if ($wa !== ''): $wa = strpos($wa, '0') === 0 ? '92' . substr($wa, 1) : $wa; ?>
                    <a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-card">
            <h2>Actions</h2>
            <?php if ($roomInMaintenance && in_array($booking['status'], ['confirmed', 'checked_in'], true)): ?>
                <div class="notice notice-error" role="status"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                    <span>Room <?= e($booking['room_number']) ?> is under maintenance.<?= $booking['status'] === 'confirmed' ? ' Move the guest to another room before checking them in, or mark the room available if it\'s ready.' : ' The guest is staying in it: move them to another room if it can\'t be used.' ?></span></div>
            <?php endif; ?>
            <?php if ($moves): ?>
                <div class="status-actions">
                    <?php foreach ($moves as $status => $label): ?>
                        <form method="post"<?= $status === 'cancelled' ? ' data-confirm="Cancel booking ' . e($booking['reference']) . '? The guest will be emailed."' : '' ?>>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="status" value="<?= $status ?>">
                            <button type="submit" class="<?= $status === 'cancelled' ? 'btn-small-outline' : 'btn-primary-red' ?> w-100"><?= e($label) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p class="admin-muted mb-0 mt-2">
                <?php if ($booking['status'] === 'confirmed' && $booking['check_in'] > $today): ?>
                    Check-in opens on <?= e(long_date($booking['check_in'])) ?>.
                <?php elseif ($booking['status'] === 'checked_out'): ?>
                    This stay is finished.
                <?php elseif ($booking['status'] === 'cancelled'): ?>
                    This booking is cancelled. The room is free again for these dates.
                <?php elseif ($booking['status'] === 'confirmed' && $booking['check_out'] <= $today): ?>
                    The guest never checked in. The stay dates have passed.
                <?php endif; ?>
            </p>
            <?php if ($invoice): ?>
                <a href="<?= url('invoice/view.php?id=' . $invoice['id']) ?>" class="btn-small-outline w-100 mt-2"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Invoice <?= e($invoice['invoice_number']) ?> (<?= $invoice['status'] === 'paid' ? 'paid' : 'unpaid' ?>)</a>
            <?php elseif ($booking['status'] === 'checked_out'): ?>
                <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="invoice">
                    <button type="submit" class="btn-small-outline w-100">Make the stay invoice</button></form>
            <?php endif; ?>
            <p class="mt-3 mb-0"><a href="<?= url('booking/confirmation.php?ref=' . urlencode($booking['reference'])) ?>" target="_blank" rel="noopener" class="admin-link">Guest's confirmation page <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a></p>
        </section>
    </div>
</div>

<?php admin_close();
