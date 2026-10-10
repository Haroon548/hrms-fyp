<?php
/*
 * Admin: table reservations (FR-TBL-06). Today's list for the restaurant host, upcoming,
 * past and cancelled, with a filter by restaurant. "Seated" marks a reservation completed;
 * "Cancel" frees the table and emails the guest.
 * Reservations whose time has passed are marked completed when this page opens (D16).
 */
require_once '../includes/init.php';
require_once '../includes/reservations.php';
require_once '../includes/admin-layout.php';
require_admin();

tidy_past_reservations();

$views = [
    'today'     => ['Today', "r.reservation_date = CURDATE() AND r.status <> 'cancelled'", 'r.reservation_time'],
    'upcoming'  => ['Upcoming', "r.reservation_date > CURDATE() AND r.status = 'confirmed'", 'r.reservation_date, r.reservation_time'],
    'past'      => ['Past', "r.reservation_date < CURDATE() AND r.status <> 'cancelled'", 'r.reservation_date DESC, r.reservation_time DESC'],
    'cancelled' => ['Cancelled', "r.status = 'cancelled'", 'r.reservation_date DESC'],
];
$view = isset($views[$_GET['view'] ?? '']) ? $_GET['view'] : 'today';
$restaurantId = (int) ($_GET['restaurant'] ?? 0);
$here = 'admin/reservations.php?' . http_build_query(array_filter(['view' => $view, 'restaurant' => $restaurantId]));

if (admin_action() === 'status') {
    $r = find_reservation((int) ($_POST['id'] ?? 0));
    $status = (string) ($_POST['status'] ?? '');
    if (!$r) {
        flash('error', 'That reservation no longer exists.');
    } elseif ($problem = change_reservation_status($r, $status)) {
        flash('error', $problem);
    } else {
        flash('success', reservation_number($r) . ($status === 'completed' ? ': ' . $r['guest_name'] . ' is seated.' : ' is cancelled and the guest has been emailed.'));
    }
    redirect($here);
}

$restaurantFilter = $restaurantId ? ' AND t.restaurant_id = ' . $restaurantId : '';
$counts = [];
foreach ($views as $key => [$label, $where]) {
    $counts[$key] = (int) db()->query("SELECT COUNT(*) FROM table_reservations r JOIN dining_tables t ON t.id = r.table_id WHERE $where$restaurantFilter")->fetchColumn();
}
[$label, $where, $orderBy] = $views[$view];
$ids = db()->query("SELECT r.id FROM table_reservations r JOIN dining_tables t ON t.id = r.table_id WHERE $where$restaurantFilter ORDER BY $orderBy LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
$list = array_map('find_reservation', $ids);
$guestsToday = array_sum(array_map(function ($r) { return $r['status'] === 'confirmed' ? (int) $r['party_size'] : 0; }, $view === 'today' ? $list : []));

live_watch('table_reservations', 'dining_tables', 'restaurants'); // updates by itself when these change
admin_open('Reservations', 'reservations', 'Table reservations. Mark guests as seated when they arrive.',
    '<a href="' . url('admin/tables.php') . '" class="btn-small-outline"><i class="fa-solid fa-chair" aria-hidden="true"></i> Tables & hours</a>');
?>

<nav class="account-tabs admin-tabs" aria-label="Reservation views">
    <?php foreach ($views as $key => [$tabLabel]): ?>
        <a href="<?= url('admin/reservations.php?' . http_build_query(array_filter(['view' => $key, 'restaurant' => $restaurantId]))) ?>" class="<?= $key === $view ? 'active' : '' ?>"<?= $key === $view ? ' aria-current="page"' : '' ?>>
            <?= e($tabLabel) ?><?php if ($counts[$key] && in_array($key, ['today', 'upcoming'], true)): ?> <span class="tab-count"><?= $counts[$key] ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<div class="admin-filter">
    <span>Restaurant:</span>
    <a href="<?= url('admin/reservations.php?view=' . $view) ?>" class="<?= $restaurantId ? '' : 'active' ?>">All</a>
    <?php foreach (restaurants_list() as $rs): ?>
        <a href="<?= url('admin/reservations.php?view=' . $view . '&restaurant=' . $rs['id']) ?>" class="<?= $restaurantId === (int) $rs['id'] ? 'active' : '' ?>"><?= e($rs['name']) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($view === 'today' && $list): ?>
    <p class="admin-muted"><?= $guestsToday ?> guests still expected today.</p>
<?php endif; ?>

<?php if (!$list): ?>
    <div class="empty-state">
        <span class="empty-icon"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span>
        <h2>No reservations here</h2>
        <p>Reservations made on the website show up here straight away.</p>
    </div>
<?php else: ?>
    <div class="table-responsive admin-table-wrap">
        <table class="table admin-table align-middle">
            <thead>
                <tr><th scope="col">Time</th><th scope="col">Guest</th><th scope="col">Party</th><th scope="col">Table</th><th scope="col">Note</th><th scope="col">Status</th><th scope="col" class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($list as $r): ?>
                    <tr>
                        <th scope="row" class="text-nowrap"><?= e(nice_time($r['reservation_time'])) ?>
                            <small class="d-block admin-muted"><?= e(date('D j M', strtotime($r['reservation_date']))) ?> · <?= e(reservation_number($r)) ?></small></th>
                        <td><?= e($r['guest_name']) ?><small class="d-block"><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $r['guest_phone'])) ?>"><?= e($r['guest_phone']) ?></a></small></td>
                        <td><?= (int) $r['party_size'] ?></td>
                        <td class="text-nowrap"><strong><?= e($r['table_number']) ?></strong><small class="d-block admin-muted"><?= e($r['restaurant']) ?></small></td>
                        <td><?= $r['notes'] !== '' ? e($r['notes']) : '<span class="admin-muted">—</span>' ?></td>
                        <td><?= status_badge($r['status'] === 'completed' ? 'seated' : $r['status']) ?></td>
                        <td class="text-end">
                            <?php if ($r['status'] === 'confirmed'): ?>
                                <div class="table-actions">
                                    <?php if ($r['reservation_date'] <= date('Y-m-d')): ?>
                                        <form method="post"><?= csrf_field() ?>
                                            <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn-small-red text-nowrap">Seated</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" data-confirm="Cancel <?= e(reservation_number($r)) ?> for <?= e($r['guest_name']) ?>? They will be emailed."><?= csrf_field() ?>
                                        <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="btn-icon tool-danger" aria-label="Cancel reservation" title="Cancel"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php admin_close();
