<?php
/*
 * Admin: occupancy chart (FR-SIG-CAL-02; design.md: Admin panel).
 * Rooms down the side (grouped by room type), 14 nights across the top, and each booking
 * as a bar coloured by status. Clicking a bar opens the booking. ‹ › move by a week.
 * The bottom row shows how full the hotel is each night.
 *   admin/occupancy.php?start=2026-11-02
 * Uses the same rule as booking (FR-SIG-CAL-03): a booking fills the nights from
 * check-in up to the night before check-out; cancelled bookings don't count.
 */
require_once '../includes/init.php';
require_once '../includes/bookings.php';
require_once '../includes/admin-layout.php';
require_admin();

const CHART_DAYS = 14;

$today = date('Y-m-d');
$start = $_GET['start'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !strtotime($start)) {
    $start = $today;
}
$end = date('Y-m-d', strtotime($start . ' +' . CHART_DAYS . ' days'));
$days = [];
for ($d = $start; $d < $end; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
    $days[] = $d;
}

$rooms = db()->query(
    'SELECT r.id, r.room_number, r.status, rt.name AS type_name
     FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id
     ORDER BY rt.sort_order, rt.name, LENGTH(r.room_number), r.room_number'
)->fetchAll();

$stmt = db()->prepare(
    "SELECT b.id, b.room_id, b.reference, b.check_in, b.check_out, b.status, u.name AS guest_name
     FROM bookings b JOIN users u ON u.id = b.user_id
     WHERE b.status <> 'cancelled' AND b.check_in < ? AND b.check_out > ?
     ORDER BY b.check_in"
);
$stmt->execute([$end, $start]);
$byRoom = [];
$booked = array_fill_keys($days, 0);
foreach ($stmt->fetchAll() as $b) {
    // Which columns the bar covers (0 = first day shown), cut off at the chart's edges
    $from = max(0, (int) round((strtotime($b['check_in']) - strtotime($start)) / 86400));
    $to = min(CHART_DAYS, (int) round((strtotime($b['check_out']) - strtotime($start)) / 86400));
    $b['col_from'] = $from;
    $b['col_to'] = $to;
    $b['cut_left'] = $b['check_in'] < $start;
    $b['cut_right'] = $b['check_out'] > $end;
    $byRoom[$b['room_id']][] = $b;
    for ($i = $from; $i < $to; $i++) {
        $booked[$days[$i]]++;
    }
}
$openRooms = count(array_filter($rooms, function ($r) { return $r['status'] === 'available'; }));

$weekBack = date('Y-m-d', strtotime($start . ' -7 days'));
$weekOn = date('Y-m-d', strtotime($start . ' +7 days'));

live_watch('bookings', 'rooms', 'room_types'); // updates by itself when these change
admin_open('Occupancy', 'occupancy', 'Who is in which room over the next two weeks. Click a bar to open the booking.');
?>

<div class="occ-toolbar">
    <div class="occ-nav">
        <a href="<?= url('admin/occupancy.php?start=' . $weekBack) ?>" class="btn-icon" aria-label="One week earlier" title="One week earlier"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
        <strong><?= e(date('j M', strtotime($start))) ?> – <?= e(date('j M Y', strtotime($end . ' -1 day'))) ?></strong>
        <a href="<?= url('admin/occupancy.php?start=' . $weekOn) ?>" class="btn-icon" aria-label="One week later" title="One week later"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
        <?php if ($start !== $today): ?><a href="<?= url('admin/occupancy.php') ?>" class="btn-small-outline">Today</a><?php endif; ?>
    </div>
    <form method="get" class="occ-jump">
        <label for="start">Go to date</label>
        <input type="date" id="start" name="start" value="<?= e($start) ?>">
        <button type="submit" class="btn-small-red">Show</button>
    </form>
    <ul class="occ-key" aria-label="Colour key">
        <li><span class="occ-swatch is-confirmed"></span> Confirmed</li>
        <li><span class="occ-swatch is-checked_in"></span> Checked in</li>
        <li><span class="occ-swatch is-checked_out"></span> Checked out</li>
        <li><span class="occ-swatch is-maintenance"></span> Under maintenance</li>
    </ul>
</div>

<?php if (!$rooms): ?>
    <div class="empty-state">
        <span class="empty-icon"><i class="fa-solid fa-door-open" aria-hidden="true"></i></span>
        <h2>No rooms yet</h2>
        <p>Add rooms on the <a href="<?= url('admin/rooms.php') ?>">Rooms page</a> to see them here.</p>
    </div>
<?php else: ?>
    <div class="occ-scroll" tabindex="0" aria-label="Occupancy chart, scrolls sideways on small screens">
        <div class="occ" style="--days: <?= CHART_DAYS ?>">
            <div class="occ-row occ-head">
                <span class="occ-label">Room</span>
                <?php foreach ($days as $day): ?>
                    <span class="occ-day<?= $day === $today ? ' is-today' : '' ?><?= date('N', strtotime($day)) >= 6 ? ' is-weekend' : '' ?>">
                        <small><?= date('D', strtotime($day)) ?></small><?= date('j', strtotime($day)) ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <?php $group = null; foreach ($rooms as $room): ?>
                <?php if ($group !== $room['type_name']): $group = $room['type_name']; ?>
                    <div class="occ-group"><?= e($group) ?></div>
                <?php endif; ?>
                <div class="occ-row<?= $room['status'] === 'maintenance' ? ' is-maintenance' : '' ?>">
                    <span class="occ-label"><?= e($room['room_number']) ?>
                        <?php if ($room['status'] === 'maintenance'): ?><i class="fa-solid fa-screwdriver-wrench" title="Under maintenance" aria-label="Under maintenance"></i><?php endif; ?></span>
                    <?php foreach ($days as $i => $day): ?>
                        <span class="occ-cell<?= $day === $today ? ' is-today' : '' ?><?= date('N', strtotime($day)) >= 6 ? ' is-weekend' : '' ?>" style="grid-column: <?= $i + 2 ?>"></span>
                    <?php endforeach; ?>
                    <?php foreach ($byRoom[$room['id']] ?? [] as $b): if ($b['col_to'] <= $b['col_from']) continue; ?>
                        <a href="<?= url('admin/booking.php?id=' . $b['id']) ?>"
                           class="occ-bar is-<?= e($b['status']) ?><?= $b['cut_left'] ? ' cut-left' : '' ?><?= $b['cut_right'] ? ' cut-right' : '' ?>"
                           style="grid-column: <?= $b['col_from'] + 2 ?> / <?= $b['col_to'] + 2 ?>"
                           title="<?= e($b['guest_name'] . ' · ' . $b['reference'] . ' · ' . short_date($b['check_in']) . ' → ' . short_date($b['check_out'])) ?>">
                            <span><?= e($b['guest_name']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="occ-row occ-foot">
                <span class="occ-label">Full</span>
                <?php foreach ($days as $day):
                    $percent = $openRooms ? (int) round($booked[$day] / $openRooms * 100) : 0; ?>
                    <span class="occ-day<?= $day === $today ? ' is-today' : '' ?>" title="<?= $booked[$day] ?> of <?= $openRooms ?> rooms booked">
                        <span class="occ-meter" style="--fill: <?= min(100, $percent) ?>%"></span><?= $percent ?>%
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php admin_close();
